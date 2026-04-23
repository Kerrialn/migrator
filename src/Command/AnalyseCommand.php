<?php

declare(strict_types=1);

namespace KerrialNewham\Migrator\Command;

use Exception;
use KerrialNewham\ComposerJsonParser\Exception\ComposerJsonNotFoundException;
use KerrialNewham\ComposerJsonParser\ComposerJson;
use KerrialNewham\Migrator\Config\Config;
use KerrialNewham\Migrator\DataTransferObject\Migration;
use KerrialNewham\Migrator\DataTransferObject\Project;
use KerrialNewham\Migrator\DataTransferObject\Upgrade;
use KerrialNewham\Migrator\Enum\FrameworkTypeEnum;
use KerrialNewham\Migrator\Enum\MigrationCalculationWeightEnum;
use KerrialNewham\Migrator\Enum\PhpVersionEnum;
use KerrialNewham\Migrator\Enum\TransitionTypeEnum;
use KerrialNewham\Migrator\Service\Calculator\MigrationCalculator;
use KerrialNewham\Migrator\Service\Calculator\UpgradeCalculator;
use KerrialNewham\Migrator\Service\FrameworkDetector;
use KerrialNewham\Migrator\Service\Migration\Analyser\TemplatingEngineAnalyser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

#[AsCommand(name: 'analyse', aliases: ['a'])]
class AnalyseCommand extends Command
{

    public function __construct(
        private readonly Project $project,
        private readonly Config $config,
        private readonly FrameworkDetector $frameworkDetector = new FrameworkDetector(),
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle(input: $input, output: $output);
        $projectPath = $this->config->getPath();
        $this->project->setPath($projectPath);
        $this->project->setExclude($this->config->getExclude());

        // 1. ask if migration or upgrade
        $transitionTypeEnum = $this->askTransitionType($io);
        if (!$transitionTypeEnum instanceof TransitionTypeEnum) {
            return Command::FAILURE;
        }
        $this->resolveTransition(transitionTypeEnum: $transitionTypeEnum);

        // 2. Ask target PHP version or source+target framework
        if ($transitionTypeEnum === TransitionTypeEnum::UPGRADE) {
            $this->resolveTarget(target: $this->askTargetPhpVersion($io));
        } else {
            $source = $this->askSourceFramework($io);
            $this->resolveTarget(target: $this->askTargetFramework($io));
            $this->project->getMigration()?->setSourceFramework($source);

            $templatingAnalyser = new TemplatingEngineAnalyser(
                path: $projectPath,
                exclude: $this->config->getExclude(),
                targetFramework: $this->project->getMigration()?->getTargetFramework() ?? FrameworkTypeEnum::NONE,
                composer: null,
            );
            if ($templatingAnalyser->hasTemplates()) {
                $includeTemplating = $io->confirm(
                    sprintf('Template files detected (%s). Include templating engine in analysis?', $templatingAnalyser->detectEngine()),
                    default: true
                );
                $this->project->getMigration()?->setIncludeTemplating($includeTemplating);
            }
        }
        $io->progressStart(max: 100);

        // 3. extract project files
        $this->extractProjectFiles(
            path: $projectPath,
            exclude: $this->config->getExclude(),
            legacyDirs: $this->config->getLegacyDirs(),
        );

        try {
            $composer = (new ComposerJson())
                ->withComposerJsonPath(path: $projectPath)
                ->withName()
                ->withRequire()
                ->withRequireDev()
                ->getComposer();

            $this->project->setComposer(composer: $composer);

        } catch (ComposerJsonNotFoundException) {
            $io->info("no composer.json found.");
            $this->project->setComposer(null);
        }

        // 4. Detect project frameworks & calculate certainty
        $detectedFrameworks = $this->frameworkDetector->detect($projectPath, $this->project->getComposer());
        foreach ($detectedFrameworks as $framework) {
            $this->project->addFramework($framework);
        }

        $primaryFramework = $this->project->getPrimaryFramework();
        $detectedCount = $this->project->getFrameworks()->count();
        if ($primaryFramework !== null) {
            $certainty = $primaryFramework->getCertainty() !== null ? sprintf(' (certainty: %s%%)', $primaryFramework->getCertainty()) : '';
            if ($detectedCount > 1) {
                $io->info(sprintf('Mixed codebase detected (%d frameworks) — primary: %s%s', $detectedCount, $primaryFramework->getFrameworkTypeEnum()->value, $certainty));
                if ($transitionTypeEnum === TransitionTypeEnum::MIGRATION) {
                    $io->warning(
                        'Mid-migration codebase detected. Legacy framework files still present alongside new code will skew coupling scores upward. ' .
                        'For an accurate picture of the new code layer, add your legacy directories to the exclude list in migrator.php.'
                    );
                }
            } else {
                $io->info(sprintf('Detected framework: %s%s', $primaryFramework->getFrameworkTypeEnum()->value, $certainty));
            }
        } else {
            $io->info('No framework detected.');
        }

        // 5. run analysis
        match ($transitionTypeEnum) {
            TransitionTypeEnum::UPGRADE => (new UpgradeCalculator())->calculate($this->project, $io),
            TransitionTypeEnum::MIGRATION => (new MigrationCalculator())->calculate($this->project, $io),
        };

        // 6. output analysis report
        $io->progressFinish();
        match ($transitionTypeEnum) {
            TransitionTypeEnum::UPGRADE => $this->printUpgradablityScore($io),
            TransitionTypeEnum::MIGRATION => $this->printMigratablityScore($io),
        };

        return Command::SUCCESS;
    }

    private function askTransitionType(SymfonyStyle $io): null|TransitionTypeEnum
    {
        $objectiveType = $io->choice(question: 'Migration or Upgrade?', choices: [
            TransitionTypeEnum::MIGRATION->value => TransitionTypeEnum::MIGRATION->value,
            TransitionTypeEnum::UPGRADE->value => TransitionTypeEnum::UPGRADE->value
        ]);

        $objectiveTypeEnum = TransitionTypeEnum::tryFrom($objectiveType);

        if (!$objectiveTypeEnum instanceof TransitionTypeEnum) {
            $io->error('Invalid objective type.');
            return null;
        }
        return $objectiveTypeEnum;
    }

    private function askSourceFramework(SymfonyStyle $io): null|FrameworkTypeEnum
    {
        $sourceFramework = $io->choice(question: 'What framework are you migrating FROM?', choices: FrameworkTypeEnum::getFrameworkOptions());
        $sourceFrameworkEnum = FrameworkTypeEnum::tryFrom(strtolower((string) $sourceFramework));
        if (!$sourceFrameworkEnum instanceof FrameworkTypeEnum) {
            $io->error('Invalid framework type.');
            return null;
        }
        return $sourceFrameworkEnum;
    }

    private function askTargetFramework(SymfonyStyle $io): null|FrameworkTypeEnum
    {
        $targetFramework = $io->choice(question: 'What is your target framework?', choices: FrameworkTypeEnum::getFrameworkOptions());
        // Symfony Console returns the array key for associative choices (e.g. 'SYMFONY'), not the value ('symfony')
        $targetFrameworkEnum = FrameworkTypeEnum::tryFrom(strtolower((string) $targetFramework));
        if (!$targetFrameworkEnum instanceof FrameworkTypeEnum) {
            $io->error('Invalid framework type.');
            return null;
        }
        return $targetFrameworkEnum;
    }

    private function askTargetPhpVersion(SymfonyStyle $io): null|PhpVersionEnum
    {
        $targetPhpVersion = $io->choice(question: 'What is your target PHP version?', choices: [
            '8.2' => PhpVersionEnum::PHP_8_2->value,
            '8.3' => PhpVersionEnum::PHP_8_3->value,
            '8.4' => PhpVersionEnum::PHP_8_4->value,
        ]);

        $targetPhpVersionEnum = PhpVersionEnum::tryFrom($targetPhpVersion);
        if (!$targetPhpVersionEnum instanceof PhpVersionEnum) {
            $io->error('Invalid objective type.');
            return null;
        }

        return $targetPhpVersionEnum;
    }

    /**
     * @param string[] $exclude
     * @param string[] $legacyDirs
     */
    private function extractProjectFiles(string $path, array $exclude = [], array $legacyDirs = []): void
    {
        $finder = new Finder();
        $finder->in($path)->exclude($exclude)->exclude($legacyDirs)->files()->name('*.php');
        foreach ($finder as $file) {
            $this->project->addFile($file);
        }

        foreach ($legacyDirs as $legacyDir) {
            $legacyPath = rtrim($path, '/') . '/' . ltrim($legacyDir, '/');
            if (!is_dir($legacyPath)) {
                continue;
            }
            $legacyFinder = new Finder();
            $legacyFinder->in($legacyPath)->exclude($exclude)->files()->name('*.php');
            foreach ($legacyFinder as $file) {
                $this->project->addLegacyFile($file);
            }
        }
    }

    private function setupUpgrade(): void
    {
        $upgrade = new Upgrade();
        $this->project->setUpgrade($upgrade);
    }

    private function setupMigration(): void
    {
        $migration = new Migration();
        $this->project->setMigration($migration);
    }

    private function resolveTransition(TransitionTypeEnum $transitionTypeEnum): void
    {
        $this->project->setTransitionTypeEnum($transitionTypeEnum);

        match ($transitionTypeEnum) {
            TransitionTypeEnum::MIGRATION => $this->setupMigration(),
            TransitionTypeEnum::UPGRADE => $this->setupUpgrade(),
        };
    }

    private function resolveTarget(PhpVersionEnum|FrameworkTypeEnum|null $target): void
    {
        match (true) {
            $target instanceof PhpVersionEnum => $this->project->getUpgrade()->setTargetPhpVersion($target),
            $target instanceof FrameworkTypeEnum => $this->project->getMigration()->setTargetFramework($target),
            default => null,
        };
    }

    private function legacyCouplingLabel(float $score): string
    {
        return match (true) {
            $score < 20 => '(heavily coupled — significant rewrite work remains)',
            $score < 45 => '(moderately coupled — meaningful work remains)',
            $score < 70 => '(lightly coupled — manageable cleanup remaining)',
            default     => '(minimal coupling — nearly migrated)',
        };
    }

    private function printUpgradablityScore(SymfonyStyle $io): void
    {
        $upgrade = $this->project->getUpgrade();

        $io->title('Project Upgradability Scores');

        $io->writeln("\n<fg=red>0-49: Difficult</>");
        $io->writeln("<fg=yellow>50-79: Medium</>");
        $io->writeln("<fg=green>80-100: Easy</>\n");

        $io->table(
            ['Metric', 'Score'],
            [
                ['Framework Version Upgradability', $upgrade->getFrameworkVersionUpgradabilityScore()],
                ['Dependencies Upgradability', $upgrade->getDependenciesUpgradabilityScore()],
                ['PHP Version Upgradability', $upgrade->getPhpVersionUpgradabilityScore()],
                ['Codebase Size Upgradability', $upgrade->getCodebaseSizeUpgradabilityScore()],
                ['Overall Score', $upgrade->getComplexity()],
            ]
        );

        $difficulty = match (true) {
            $upgrade->getComplexity() >= 0 && $upgrade->getComplexity() < 50 => "Difficult Upgrade",
            $upgrade->getComplexity() >= 50 && $upgrade->getComplexity() < 80 => "Medium Upgrade",
            $upgrade->getComplexity() >= 80 && $upgrade->getComplexity() <= 100 => "Easy Upgrade",
            default => throw new Exception('Unexpected complexity value'),
        };

        $io->success($difficulty);
    }

    private function printMigratablityScore(SymfonyStyle $io): void
    {
        $migration = $this->project->getMigration();
        if ($migration === null) {
            return;
        }

        $io->title('Project Migratability Scores');

        $io->writeln("\n<fg=red>0-35: Extremely Difficult</>");
        $io->writeln("<fg=red>35-55: Very Difficult</>");
        $io->writeln("<fg=yellow>55-70: Difficult</>");
        $io->writeln("<fg=yellow>70-85: Moderate</>");
        $io->writeln("<fg=green>85-100: Straightforward</>\n");

        $weights = MigrationCalculationWeightEnum::getWeights($migration->isIncludeTemplating());
        $rows = [
            ['Framework Coupling', $migration->getFrameworkCouplingScore(), $weights[MigrationCalculationWeightEnum::FRAMEWORK_COUPLING->name] . '%'],
            ['Database Coupling', $migration->isDatabaseLayerDetected() ? $migration->getDatabaseCouplingScore() : 'No database layer detected', $weights[MigrationCalculationWeightEnum::DATABASE_COUPLING->name] . '%'],
            ['Dependency Compatibility', $migration->getDependencyCompatibilityScore(), $weights[MigrationCalculationWeightEnum::DEPENDENCY_COMPATIBILITY->name] . '%'],
            ['Architecture Quality', $migration->getArchitectureScore(), $weights[MigrationCalculationWeightEnum::ARCHITECTURE->name] . '%'],
            ['Test Coverage', $migration->getTestCoverageScore(), $weights[MigrationCalculationWeightEnum::TEST_COVERAGE->name] . '%'],
            ['Codebase Size', $migration->getCodeSizeScore(), $weights[MigrationCalculationWeightEnum::CODEBASE_SIZE->name] . '%'],
        ];

        if ($migration->isIncludeTemplating()) {
            $engineLabel = sprintf('Templating (%s, %d files)', $migration->getDetectedEngine(), $migration->getTemplateFileCount());
            $rows[] = [$engineLabel, $migration->getTemplatingScore(), $weights[MigrationCalculationWeightEnum::TEMPLATING->name] . '%'];
        }

        $rows[] = ['Overall Score', $migration->getComplexity(), '—'];

        $io->table(['Metric', 'Score', 'Weight'], $rows);

        if ($migration->hasLegacyData()) {
            $newFileCount = $this->project->getFiles()->count();
            $legacyFileCount = $migration->getLegacyFileCount();
            $totalFiles = $newFileCount + $legacyFileCount;
            $progressPct = $totalFiles > 0 ? round(($newFileCount / $totalFiles) * 100, 1) : 0;

            $io->section('Legacy Code Remaining');
            $io->table(
                ['Metric', 'Value'],
                [
                    ['Legacy files', number_format($legacyFileCount)],
                    ['New code files', number_format($newFileCount)],
                    ['Migration progress (by file count)', $progressPct . '%'],
                    ['Legacy coupling score', sprintf('%s  %s', $migration->getLegacyCouplingScore(), $this->legacyCouplingLabel($migration->getLegacyCouplingScore()))],
                ]
            );
        }

        $difficulty = match (true) {
            $migration->getComplexity() >= 0 && $migration->getComplexity() < 35 => "Extremely Difficult Migration",
            $migration->getComplexity() >= 35 && $migration->getComplexity() < 55 => "Very Difficult Migration",
            $migration->getComplexity() >= 55 && $migration->getComplexity() < 70 => "Difficult Migration",
            $migration->getComplexity() >= 70 && $migration->getComplexity() < 85 => "Moderate Migration",
            $migration->getComplexity() >= 85 && $migration->getComplexity() <= 100 => "Straightforward Migration",
            default => throw new Exception('Unexpected complexity value'),
        };

        $io->success($difficulty);
    }

}
