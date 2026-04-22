<?php

declare(strict_types=1);

namespace KerrialNewham\Migrator\Service\Calculator;

use KerrialNewham\Migrator\DataTransferObject\Migration;
use KerrialNewham\Migrator\DataTransferObject\Project;
use KerrialNewham\Migrator\Enum\FrameworkTypeEnum;
use KerrialNewham\Migrator\Enum\MigrationCalculationWeightEnum;
use KerrialNewham\Migrator\Service\Calculator\Contract\CalculatorInterface;
use KerrialNewham\Migrator\Service\Migration\Analyser\ArchitectureAnalyser;
use KerrialNewham\Migrator\Service\Migration\Analyser\DependencyCompatibilityAnalyser;
use KerrialNewham\Migrator\Service\Migration\Analyser\FrameworkCouplingAnalyser;
use KerrialNewham\Migrator\Service\Migration\Analyser\DatabaseCouplingAnalyser;
use KerrialNewham\Migrator\Service\Migration\Analyser\TestCoverageAnalyser;
use Symfony\Component\Console\Style\SymfonyStyle;

final readonly class MigrationCalculator implements CalculatorInterface
{
    public function calculate(Project $project, SymfonyStyle $io): void
    {
        $migration = $project->getMigration();
        if ($migration === null) {
            return;
        }

        $files = $project->getFiles();
        $primaryFramework = $project->getPrimaryFramework();
        $sourceFramework = $primaryFramework?->getFrameworkTypeEnum() ?? FrameworkTypeEnum::NONE;
        $targetFramework = $migration->getTargetFramework();

        $io->info('analysing framework coupling');
        $migration->setFrameworkCouplingScore(
            (new FrameworkCouplingAnalyser($files, $sourceFramework, $targetFramework))->analyse()
        );
        $io->progressAdvance(20);

        $io->info('analysing database coupling');
        $dbAnalyser = new DatabaseCouplingAnalyser($files);
        $migration->setDatabaseCouplingScore($dbAnalyser->analyse());
        $migration->setDatabaseLayerDetected($dbAnalyser->isDatabaseLayerDetected());
        $io->progressAdvance(20);

        $io->info('analysing dependency compatibility');
        $migration->setDependencyCompatibilityScore(
            (new DependencyCompatibilityAnalyser($project->getComposer(), $sourceFramework, $targetFramework))->analyse()
        );
        $io->progressAdvance(20);

        $io->info('analysing architecture');
        $migration->setArchitectureScore(
            (new ArchitectureAnalyser($files))->analyse()
        );
        $io->progressAdvance(20);

        $io->info('analysing test coverage');
        $migration->setTestCoverageScore(
            (new TestCoverageAnalyser($files))->analyse()
        );
        $io->progressAdvance(20);

        $migration->setComplexity($this->calculateTotalScore($migration));
    }

    private function calculateTotalScore(Migration $migration): float
    {
        $scores = [
            MigrationCalculationWeightEnum::FRAMEWORK_COUPLING->name => $migration->getFrameworkCouplingScore(),
            MigrationCalculationWeightEnum::DATABASE_COUPLING->name => $migration->getDatabaseCouplingScore(),
            MigrationCalculationWeightEnum::DEPENDENCY_COMPATIBILITY->name => $migration->getDependencyCompatibilityScore(),
            MigrationCalculationWeightEnum::ARCHITECTURE->name => $migration->getArchitectureScore(),
            MigrationCalculationWeightEnum::TEST_COVERAGE->name => $migration->getTestCoverageScore(),
        ];

        $totalWeightedScore = 0.0;
        foreach (MigrationCalculationWeightEnum::getWeights() as $name => $weight) {
            $totalWeightedScore += ($scores[$name] * $weight) / 100;
        }

        return round($totalWeightedScore, 2);
    }
}
