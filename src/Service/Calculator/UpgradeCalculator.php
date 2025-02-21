<?php

namespace KerrialNewham\Migrator\Service\Calculator;

use Doctrine\Common\Collections\ArrayCollection;
use KerrialNewham\ComposerJsonParser\Model\Composer;
use KerrialNewham\ComposerJsonParser\Model\Package;
use KerrialNewham\Migrator\DataTransferObject\PackageVersionInfo;
use KerrialNewham\Migrator\DataTransferObject\Project;
use KerrialNewham\Migrator\Enum\FrameworkTypeEnum;
use KerrialNewham\Migrator\Enum\UpgradeCalculationWeightEnum;
use KerrialNewham\Migrator\Service\Calculator\Contract\CalculatorInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final readonly class UpgradeCalculator implements CalculatorInterface
{
    public function calculate(Project $project, SymfonyStyle $io): void
    {
        $frameworkVersionUpgradabilityScore = $this->getFrameworkVersionUpgradabilityScore($project, $io);
        $project->getUpgrade()->setFrameworkVersionUpgradabilityScore($frameworkVersionUpgradabilityScore);

        $dependenciesUpgradabilityScore = $this->getDependenciesUpgradabilityScore($project, $io);
        $project->getUpgrade()->setDependenciesUpgradabilityScore($dependenciesUpgradabilityScore);

        $phpVersionUpgradabilityScore = $this->getPhpVersionUpgradabilityScore($project, $io);
        $project->getUpgrade()->setPhpVersionUpgradabilityScore($phpVersionUpgradabilityScore);

        $codebaseSizeUpgradabilityScore = $this->getCodebaseSizeUpgradabilityScore($project, $io);
        $project->getUpgrade()->setCodebaseSizeUpgradabilityScore($codebaseSizeUpgradabilityScore);

        $totalScore = $this->calculateTotalScore(project: $project);
        $project->getUpgrade()->setComplexity($totalScore);
    }

    private function getDependenciesUpgradabilityScore(Project $project, SymfonyStyle $io): float
    {
        $io->info('checking dependencies upgradability');
        $outdatedDependencies = $this->getOutdatedDependencies($project->getComposer());
        $totalScore = 0;

        /**
         * @var PackageVersionInfo $packageVersionInfo
         */
        foreach ($outdatedDependencies as $packageVersionInfo) {
            $io->progressAdvance();
            $totalScore += $packageVersionInfo->getDifferenceBetweenVersions();
        }

        $totalDependencies = count($outdatedDependencies);
        if ($totalDependencies === 0) {
            return 100;
        }

        $maxPossibleDifference = 10;
        $maxTotalScore = $totalDependencies * $maxPossibleDifference;

        $percentageScore = ($totalScore / $maxTotalScore) * 100;

        $score = 100 - $percentageScore;

        return round(max(0, min(100, $score)), 2);
    }

    private function getLatestPhpVersion(): ?string
    {
        $url = "https://www.php.net/releases/?json";
        $response = file_get_contents($url);

        if ($response === false) {
            return null;
        }

        $data = json_decode($response, true);
        $latestVersion = '';
        foreach ($data as $majorVersion => $details) {
            if (!empty($details['version']) && version_compare($details['version'], $latestVersion, '>')) {
                $latestVersion = $details['version'];
            }
        }

        return $latestVersion ?: null;
    }

    private function getPhpVersionUpgradabilityScore(Project $project, SymfonyStyle $io): float
    {
        $io->info('checking PHP version upgradability');
        $currentPhpPackage = $project->getComposer()->getRequire()->findFirst(fn(int $key, Package $package): bool => str_contains($package->getName(), 'php'));

        if (!$currentPhpPackage) {
            return 0;  // No PHP version found, hard upgrade (or skip entirely)
        }

        $currentPhpVersion = $currentPhpPackage->getPackageVersion()->getVersionString();
        $latestPhpVersion = $this->getLatestPhpVersion();

        $majorDiff = $this->getMajorVersionDifference($currentPhpVersion, $latestPhpVersion);
        $minorDiff = $this->getMinorVersionDifference($currentPhpVersion, $latestPhpVersion);

        // Now invert the logic: higher difference means harder upgrade
        $score = 100 - ($majorDiff * 15);  // The higher the major difference, the lower the score

        // If the major difference is 0, account for minor differences
        if ($majorDiff === 0) {
            $score += $minorDiff * 3;  // Minor diff adds but doesn't overshadow
        }

        // Ensure the score stays within the 0-100 range
        $score = max(0, min(100, round($score, 2)));

        return $score;
    }

    private function getCodebaseSizeUpgradabilityScore(Project $project, SymfonyStyle $io): float
    {
        $io->info('checking codebase size upgradability');

        $phpFiles = $project->getFiles();
        if ($phpFiles->isEmpty()) {
            return 100;
        }

        $totalLines = 0;
        foreach ($phpFiles as $file) {
            $totalLines += count(file($file->getRealPath()));
            $io->progressAdvance();
        }

        $minLines = 5000;
        $maxLines = 500000;
        $totalLines = max($minLines, min($totalLines, $maxLines));
        $normalizedSize = log($maxLines - $totalLines + 1) / log($maxLines - $minLines + 1);
        $score = round($normalizedSize * 100, 2);

        return max(0, min(100, $score));
    }

    private function getFrameworkVersionUpgradabilityScore(Project $project, SymfonyStyle $io): float
    {
        $io->info('checking framework upgradability');
        $frameworks = $project->getFrameworks();

        if ($frameworks->isEmpty()) {
            return 100;  // No frameworks, it's an easy upgrade
        }

        $totalScore = 0;
        $frameworkCount = count($frameworks);

        foreach ($frameworks as $framework) {
            $currentVersion = $framework->getPackageVersion()->getVersionString();
            $targetFrameworkVersion = FrameworkTypeEnum::getTargetFrameworkVersion(
                $framework->getFrameworkTypeEnum(),
                $project->getUpgrade()->getTargetPhpVersion()
            );

            if ($targetFrameworkVersion === null) {
                $score = 100;  // No target version, assume no upgrade (easy)
            } else {
                $majorDifference = $this->getMajorVersionDifference($currentVersion, $targetFrameworkVersion);
                $minorDifference = $this->getMinorVersionDifference($currentVersion, $targetFrameworkVersion);
                $totalVersionDifference = $majorDifference * 10 + $minorDifference * 5;

                // Use a logarithmic function to penalize larger version differences
                $logDifference = log(1 + $totalVersionDifference, 2);  // Log scale, base 2 for more gradual increase
                $score = 100 - min(100, round($logDifference * 20));  // Penalize larger differences with a max cap of 100
            }

            $totalScore += $score;
            $io->progressAdvance();
        }

        return round($totalScore / $frameworkCount, 2);
    }

    private function calculateTotalScore(Project $project): float
    {
        $scores = [
            UpgradeCalculationWeightEnum::FRAMEWORK_VERSION->name => $project->getUpgrade()->getFrameworkVersionUpgradabilityScore(),
            UpgradeCalculationWeightEnum::DEPENDENCIES->name => $project->getUpgrade()->getDependenciesUpgradabilityScore(),
            UpgradeCalculationWeightEnum::PHP_VERSION->name => $project->getUpgrade()->getPhpVersionUpgradabilityScore(),
            UpgradeCalculationWeightEnum::CODEBASE_SIZE->name => $project->getUpgrade()->getCodebaseSizeUpgradabilityScore(),
        ];

        $totalWeightedScore = 0;
        foreach (UpgradeCalculationWeightEnum::getWeights() as $name => $weight) {
            $totalWeightedScore += ($scores[$name] * $weight) / 100;
        }

        return round($totalWeightedScore, 2);
    }

    private function getOutdatedDependencies(Composer $composer): ArrayCollection
    {
        /**
         * @var ArrayCollection<int, PackageVersionInfo> $outdatedPackages
         */
        $outdatedPackages = new ArrayCollection();

        foreach ($composer->getRequire() as $package) {
            if (str_contains('php', $package->getName()) || empty($package->getPackageVersion())) {
                continue;
            }

            $latestVersion = $this->getLatestPackageVersion($package->getName());
            if ($latestVersion && version_compare($package->getPackageVersion()->getVersionString(), $latestVersion, '<')) {
                $differenceBetweenVersions = $this->getMajorVersionDifference(currentVersion: $package->getPackageVersion()->getVersion(), latestVersion: $latestVersion);
                $outdatedPackages->add(
                    new PackageVersionInfo(packageName: $package->getName(), currentVersion: $package->getPackageVersion()->getVersionString(), latestVersion: $latestVersion, differenceBetweenVersions: $differenceBetweenVersions)
                );
            }
        }

        return $outdatedPackages;
    }

    private function getMajorVersionDifference($currentVersion, $latestVersion): int
    {
        $currentVersion = preg_replace('/[^0-9.]/', '', (string)$currentVersion);
        $latestVersion = preg_replace('/[^0-9.]/', '', (string)$latestVersion);

        $current = explode('.', (string)$currentVersion);
        $latest = explode('.', (string)$latestVersion);

        $currentMajor = (int)$current[0];
        $latestMajor = (int)$latest[0];

        return abs($latestMajor - $currentMajor);
    }

    private function getMinorVersionDifference($currentVersion, $latestVersion): int
    {
        $currentVersion = preg_replace('/[^0-9.]/', '', (string)$currentVersion);
        $latestVersion = preg_replace('/[^0-9.]/', '', (string)$latestVersion);

        $current = explode('.', (string)$currentVersion);
        $latest = explode('.', (string)$latestVersion);

        $currentMinor = isset($current[1]) ? (int)$current[1] : 0;
        $latestMinor = isset($latest[1]) ? (int)$latest[1] : 0;

        return abs($latestMinor - $currentMinor);
    }

    private function getLatestPackageVersion(string $packageName): ?string
    {
        if (str_contains($packageName, 'ext')) {
            return null;
        }

        $packagistUrl = "https://repo.packagist.org/p2/{$packageName}.json";

        try {
            $response = file_get_contents($packagistUrl);
            if (!$response) {
                return null;
            }

            $data = json_decode($response, true);
            if (!isset($data['packages'][$packageName])) {
                return null;
            }

            $versions = array_map(fn($package): mixed => $package['version_normalized'], $data['packages'][$packageName]);

            usort($versions, 'version_compare');
            return end($versions);
        } catch (\Exception) {
            return null;
        }
    }

}

