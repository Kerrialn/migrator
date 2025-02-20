<?php

declare(strict_types=1);

namespace KerrialNewham\Migrator\Command;

use KerrialNewham\ComposerJsonParser\Exception\ComposerJsonNotFoundException;
use KerrialNewham\ComposerJsonParser\Model\Composer;
use KerrialNewham\ComposerJsonParser\Model\Package;
use KerrialNewham\ComposerJsonParser\Parser;
use KerrialNewham\Migrator\Data\Frameworks;
use KerrialNewham\Migrator\DataTransferObject\Project;
use KerrialNewham\Migrator\DataValueObject\Framework;
use KerrialNewham\Migrator\DataValueObject\FrameworkPackage;
use KerrialNewham\Migrator\Enum\FrameworkTypeEnum;
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
        private Project $project,
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

        $transitionTypeEnum = $this->askTransitionType($io);
        if (!$transitionTypeEnum instanceof TransitionTypeEnum) {
            return Command::FAILURE;
        }
        $this->project->getTransition()->setTransitionTypeEnum($transitionTypeEnum);

        $target = match ($transitionTypeEnum) {
            TransitionTypeEnum::MIGRATION => $this->askTargetFramework($io),
            TransitionTypeEnum::UPGRADE => $this->askTargetPHPVersion($io),
        };

        $io->progressStart(100);
        $this->extractProjectFiles($projectPath);

        try {
            $composer = (new Parser())
                ->withName()
                ->withRequire()
                ->getComposer();

            $this->project->setComposer($composer);
            $this->resolveFramework($composer);

        } catch (ComposerJsonNotFoundException $exception) {
            $io->info("no composer.json found.");
            $this->project->setComposer(null);
        }

        match ($transitionTypeEnum) {
            TransitionTypeEnum::UPGRADE => (new UpgradeCalculator())->calculate($this->project),
            TransitionTypeEnum::MIGRATION => (new MigrationCalculator())->calculate($this->project),
        };


        $io->progressFinish();
        $io->success("complete");
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

    private function resolveFramework(Composer $composer): void
    {
        $frameworks = Frameworks::getFrameworks();

        $detectedFrameworks = $frameworks->filter(fn(FrameworkPackage $framework) => $composer->getRequire()->exists(
            fn(int $key, Package $package) => $package->getName() === $framework->getName()
        )
        );

        foreach ($detectedFrameworks as $framework) {
            $weight = $framework->getWeight();
            $matchingExtras = $framework->getFrameworkPackages()->filter(
                fn(FrameworkPackage $extraPackage) => $composer->getRequire()->exists(
                    fn(int $key, Package $package) => $package->getName() === $extraPackage->getName()
                )
            );

            $extraWeight = array_sum($matchingExtras->map(
                fn(FrameworkPackage $extraPackage) => $extraPackage->getWeight()
            )->toArray());

            $totalWeight = min($weight + $extraWeight, 100);
            $this->project->addFramework(new Framework(
                name: $framework->getName(),
                frameworkTypeEnum: $framework->getType(),
                certainty: $totalWeight
            ));
        }
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

    private function askTargetPHPVersion(SymfonyStyle $io): float
    {
        $targetPhpVersion = $io->choice(question: 'What is your target PHP version?', choices: [
            '8.2' => 8.2,
            '8.3' => 8.3,
            '8.4' => 8.4,
        ]);

        return (float)$targetPhpVersion;
    }

    private function extractProjectFiles(string $projectPath): void
    {
        $finder = new Finder();
        $finder->in($projectPath)->files()->name('*.php');

        foreach ($finder as $file) {
            $this->project->addFile($file);
        }
    }

}
