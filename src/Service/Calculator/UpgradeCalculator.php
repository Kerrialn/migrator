<?php

namespace KerrialNewham\Migrator\Service\Calculator;

use Carbon\CarbonImmutable;
use KerrialNewham\ComposerJsonParser\Model\Package;
use KerrialNewham\Migrator\DataTransferObject\Project;
use KerrialNewham\Migrator\Enum\FrameworkTypeEnum;
use KerrialNewham\Migrator\Enum\UpgradeCalculationWeightEnum;
use KerrialNewham\Migrator\Service\Calculator\Contract\CalculatorInterface;

final readonly class UpgradeCalculator implements CalculatorInterface
{
    public function calculate(Project $project): void
    {
        $scores = [
            UpgradeCalculationWeightEnum::FRAMEWORK_VERSION->name => $this->getFrameworkVersionScore($project),
//            UpgradeCalculationWeightEnum::DEPENDENCIES->name => $this->getDependenciesScore($project),
//            UpgradeCalculationWeightEnum::CUSTOM_CODE->name => $this->getCustomCodeScore($project),
//            UpgradeCalculationWeightEnum::CODEBASE_SIZE->name => $this->getCodebaseSizeScore($project),
//            UpgradeCalculationWeightEnum::LEGACY_PATTERNS->name => $this->getLegacyPatternsScore($project),
//            UpgradeCalculationWeightEnum::DATABASE_ORM->name => $this->getDatabaseOrmScore($project),
//            UpgradeCalculationWeightEnum::TESTING_CI->name => $this->getTestingCiScore($project),
//            UpgradeCalculationWeightEnum::PHP_VERSION->name => $this->getPhpVersionScore($project),
        ];

        $totalWeightedScore = 0;
        foreach (UpgradeCalculationWeightEnum::getWeights() as $name => $weight) {
            $totalWeightedScore += ($scores[$name] * ($weight / 100));
        }

        $complexity = round($totalWeightedScore, 2);
        $project->getTransition()->setComplexity($complexity);
    }

    private function getFrameworkVersionScore(Project $project): int
    {
        return match ($project->getPrimaryFramework()->getFrameworkTypeEnum()) {
            FrameworkTypeEnum::SYMFONY => $this->calculateSymfonyFrameworkVersionScore($project),
        };
    }

    private function getDependenciesScore(Project $project): int
    {
        return count($project->getOutdatedDependencies()) > 10 ? 80 : 40;
    }

    private function getCustomCodeScore(Project $project): int
    {
        return $project->hasCustomOverrides() ? 90 : 30;
    }

    private function getCodebaseSizeScore(Project $project): int
    {
        return min(100, $project->getCodebaseSize() / 5000 * 100);
    }

    private function getLegacyPatternsScore(Project $project): int
    {
        return $project->usesLegacyPatterns() ? 80 : 20;
    }

    private function getDatabaseOrmScore(Project $project): int
    {
        return $project->hasDatabaseBreakingChanges() ? 85 : 40;
    }

    private function getTestingCiScore(Project $project): int
    {
        return $project->hasTests() ? 20 : 80;
    }

    private function getPhpVersionScore(Project $project): int
    {
        return $project->isPhpVersionOutdated() ? 90 : 30;
    }

    private function calculateSymfonyFrameworkVersionScore(Project $project): int
    {
        $symfonyFrameworkPackage = $project->getComposer()->getRequire()->findFirst(
            fn(int $key, Package $package) => $package->getName() === $project->getPrimaryFramework()
        );

        var_dump($symfonyFrameworkPackage);
        exit();

        $url = 'https://endoflife.date/api/symfony.json';
        $response = file_get_contents($url);
        $versions = json_decode($response, true);

        foreach ($versions as $version) {
            $installedVersion = (string)$symfonyFrameworkPackage->getPackageVersion()->getVersion();
            $latestVersion = $version['latest'];

            if (str_starts_with($latestVersion, $installedVersion)) {
                $endOfLifeAt = CarbonImmutable::parse($version['eol']);
                $now = CarbonImmutable::now();

                $daysUntilEOL = round($now->diffInDays($endOfLifeAt, false),2 );
                $project->getPrimaryFramework()->setDaysUntilEndOfLife($daysUntilEOL);

                return match (true) {
                    $daysUntilEOL <= 0 => 100,
                    $daysUntilEOL <= 30 => 90,
                    $daysUntilEOL <= 90 => 75,
                    $daysUntilEOL <= 180 => 50,
                    $daysUntilEOL <= 365 => 30,
                    default => 10,
                };
            }
        }

        return 100;
    }

}

