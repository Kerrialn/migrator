<?php

declare(strict_types=1);

namespace KerrialNewham\Migrator\Command;

use Exception;
use KerrialNewham\ComposerJsonParser\Exception\ComposerJsonNotFoundException;
use KerrialNewham\ComposerJsonParser\Model\Composer;
use KerrialNewham\ComposerJsonParser\Model\Package;
use KerrialNewham\ComposerJsonParser\Parser;
use KerrialNewham\Migrator\Data\Frameworks;
use KerrialNewham\Migrator\DataTransferObject\Migration;
use KerrialNewham\Migrator\DataTransferObject\Project;
use KerrialNewham\Migrator\DataTransferObject\Upgrade;
use KerrialNewham\Migrator\DataValueObject\Framework;
use KerrialNewham\Migrator\DataValueObject\FrameworkPackage;
use KerrialNewham\Migrator\Enum\FrameworkTypeEnum;
use KerrialNewham\Migrator\Enum\PhpVersionEnum;
use KerrialNewham\Migrator\Enum\TransitionTypeEnum;
use KerrialNewham\Migrator\Service\Calculator\MigrationCalculator;
use KerrialNewham\Migrator\Service\Calculator\UpgradeCalculator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

#[AsCommand(name: 'analyse', aliases: ['a'])]
class AnalyseCommand extends Command
{

    public function __construct(
        private readonly Project $project,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('path', InputArgument::REQUIRED, 'path of the project you want to analyse.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle(input: $input, output: $output);
        $projectPath = $input->getArgument('path');
        $this->project->setPath($projectPath);

        // 1. ask if migration or upgrade
        $transitionTypeEnum = $this->askTransitionType($io);
        if (!$transitionTypeEnum instanceof TransitionTypeEnum) {
            return Command::FAILURE;
        }
        if ($transitionTypeEnum === TransitionTypeEnum::MIGRATION) {
            $this->abortCommand($io);
            return Command::INVALID;
        }
        $this->resolveTransition(transitionTypeEnum: $transitionTypeEnum);

        // 2. Ask which target framework or php version
        $target = match ($transitionTypeEnum) {
            TransitionTypeEnum::UPGRADE => $this->askTargetPhpVersion($io),
            TransitionTypeEnum::MIGRATION => $this->askTargetFramework($io),
        };


        $this->resolveTarget(target: $target);
        $io->progressStart(max: 100);

        // 3. extract project files
        $this->extractProjectFiles(path: $projectPath);

        try {
            $composer = (new Parser())
                ->withComposerJsonPath(path: $projectPath)
                ->withName()
                ->withRequire()
                ->getComposer();

            $this->project->setComposer(composer: $composer);

        } catch (ComposerJsonNotFoundException) {
            $io->info("no composer.json found.");
            $this->project->setComposer(null);
        }

        // 4. Detect project frameworks & calculate certainty
        $this->resolveFrameworks(composer: $this->project->getComposer());

        // 5. run Upgrade or Migration analysis
        match ($transitionTypeEnum) {
            TransitionTypeEnum::UPGRADE => (new UpgradeCalculator())->calculate($this->project, $io),
            TransitionTypeEnum::MIGRATION => (new MigrationCalculator())->calculate($this->project, $io),
        };

        // 5. output analysis report
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

    private function resolveFrameworks(Composer $composer): void
    {
        $frameworks = Frameworks::getFrameworks();
        $detectedFrameworks = $frameworks->filter(fn(FrameworkPackage $framework) => $composer->getRequire()->exists(
            fn(int $key, Package $package): bool => $package->getName() === $framework->getName()
        ));

        foreach ($detectedFrameworks as $framework) {
            $package = $composer->getRequire()->findFirst(
                fn(int $key, Package $package): bool => $package->getName() === $framework->getName()
            );

            if (!$package) {
                continue;
            }

            $certainty = $this->calculateFrameworkCertainty($composer, $framework->getType());

            $this->project->addFramework(new Framework(
                name: $framework->getName(),
                packageVersion: $package->getPackageVersion(),
                frameworkTypeEnum: $framework->getType(),
                certainty: $certainty
            ));
        }
    }

    private function calculateFrameworkCertainty(Composer $composer, FrameworkTypeEnum $frameworkType): float
    {
        $frameworks = Frameworks::getFrameworks();
        $selectedFrameworks = $frameworks->filter(
            fn(FrameworkPackage $framework): bool => $framework->getType() === $frameworkType
        );

        $totalWeight = 0;

        foreach ($selectedFrameworks as $framework) {
            if ($composer->getRequire()->exists(fn(int $key, Package $package): bool => $package->getName() === $framework->getName())) {
                $totalWeight += $framework->getWeight();
            }

            $matchingExtras = $framework->getFrameworkPackages()->filter(fn(FrameworkPackage $extraPackage) => $composer->getRequire()->exists(fn(int $key, Package $package): bool => $package->getName() === $extraPackage->getName())
            );

            $extraWeight = array_sum($matchingExtras->map(fn(FrameworkPackage $extraPackage): int => $extraPackage->getWeight())->toArray());
            $totalWeight += $extraWeight;
        }

        return min($totalWeight, 100);
    }

    private function askTargetFramework(SymfonyStyle $io): null|FrameworkTypeEnum
    {
        $frameworkType = $io->choice(question: 'What is your target framework?', choices: [
            FrameworkTypeEnum::SYMFONY->value => FrameworkTypeEnum::SYMFONY->value,
            FrameworkTypeEnum::LARAVEL->value => FrameworkTypeEnum::LARAVEL->value,
            FrameworkTypeEnum::TEMPEST->value => FrameworkTypeEnum::TEMPEST->value,
        ]);

        $frameworkTypeEnum = FrameworkTypeEnum::tryFrom($frameworkType);

        if (!$frameworkTypeEnum instanceof FrameworkTypeEnum) {
            $io->error('Invalid objective type.');
            return null;
        }

        return $frameworkTypeEnum;
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

    private function extractProjectFiles(string $path): void
    {
        $finder = new Finder();
        $finder->in($path)->exclude(['vendor'])->files()->name('*.php');

        foreach ($finder as $file) {
            $this->project->addFile($file);
        }
    }

    private function setupUpgrade(): void
    {
        $upgrade = new Upgrade();
        $this->project->setUpgrade($upgrade);
    }

    private function setupMigration()
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
        $io->title('Migration coming soon');
    }

    private function abortCommand(SymfonyStyle $io): int
    {
        $io->error("Migration analysis is a work in progress, coming soon!");
        return Command::INVALID;
    }

}
