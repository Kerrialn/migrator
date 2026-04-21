<?php

declare(strict_types=1);

namespace KerrialNewham\Migrator\Service;

use Doctrine\Common\Collections\ArrayCollection;
use KerrialNewham\ComposerJsonParser\Model\Composer;
use KerrialNewham\ComposerJsonParser\Model\Package;
use KerrialNewham\Migrator\Data\Frameworks;
use KerrialNewham\Migrator\DataValueObject\Framework;
use KerrialNewham\Migrator\DataValueObject\FrameworkPackage;
use KerrialNewham\Migrator\Enum\FrameworkTypeEnum;

final readonly class FrameworkDetector
{
    private const int MIN_CERTAINTY_THRESHOLD = 40;
    private const array UNSTABLE_VERSION_INDICATORS = ['dev-', '*', '@dev', '@alpha', '@beta', '@RC'];

    /**
     * @return ArrayCollection<int, Framework>
     */
    public function detect(string $projectPath, ?Composer $composer): ArrayCollection
    {
        $results = [];

        if ($composer !== null) {
            $results = $this->detectFromComposer($composer);
        }

        $this->boostWithFilesystem($projectPath, $results);
        $this->detectFilesystemOnly($projectPath, $results);

        return new ArrayCollection(
            array_values(
                array_filter($results, fn(Framework $f): bool => $f->getCertainty() >= self::MIN_CERTAINTY_THRESHOLD)
            )
        );
    }

    /**
     * @return array<string, Framework>
     */
    private function detectFromComposer(Composer $composer): array
    {
        $allPackages = new ArrayCollection([
            ...$composer->getRequire()->toArray(),
            ...$composer->getRequireDev()->toArray(),
        ]);

        $results = [];

        foreach (Frameworks::getFrameworks() as $frameworkPackage) {
            $certainty = $this->calculateCertainty($allPackages, $frameworkPackage);

            if ($certainty === 0.0) {
                continue;
            }

            $representativePackage = $this->findRepresentativePackage($allPackages, $frameworkPackage);
            if ($representativePackage === null) {
                continue;
            }

            $results[$frameworkPackage->getType()->value] = new Framework(
                name: $representativePackage->getName(),
                packageVersion: $representativePackage->getPackageVersion(),
                frameworkTypeEnum: $frameworkPackage->getType(),
                certainty: $certainty
            );
        }

        return $results;
    }

    /**
     * @param ArrayCollection<int, Package> $allPackages
     */
    private function calculateCertainty(ArrayCollection $allPackages, FrameworkPackage $frameworkPackage): float
    {
        $totalWeight = 0;

        $primaryMatch = $allPackages->findFirst(
            fn(int $key, Package $package): bool => $package->getName() === $frameworkPackage->getName()
        );

        if ($primaryMatch !== null) {
            $totalWeight += (int) round($frameworkPackage->getWeight() * $this->versionStabilityMultiplier($primaryMatch));
        }

        foreach ($frameworkPackage->getFrameworkPackages() as $subPackage) {
            $subMatch = $allPackages->findFirst(
                fn(int $key, Package $package): bool => $package->getName() === $subPackage->getName()
            );
            if ($subMatch !== null) {
                $totalWeight += (int) round($subPackage->getWeight() * $this->versionStabilityMultiplier($subMatch));
            }
        }

        return min((float) $totalWeight, 100.0);
    }

    private function versionStabilityMultiplier(Package $package): float
    {
        $versionString = $package->getPackageVersion()?->getVersionString() ?? '';
        foreach (self::UNSTABLE_VERSION_INDICATORS as $indicator) {
            if (str_contains($versionString, $indicator)) {
                return 0.5;
            }
        }
        return 1.0;
    }

    /**
     * @param ArrayCollection<int, Package> $allPackages
     */
    private function findRepresentativePackage(ArrayCollection $allPackages, FrameworkPackage $frameworkPackage): ?Package
    {
        $primaryMatch = $allPackages->findFirst(
            fn(int $key, Package $package): bool => $package->getName() === $frameworkPackage->getName()
        );
        if ($primaryMatch !== null) {
            return $primaryMatch;
        }

        foreach ($frameworkPackage->getFrameworkPackages() as $subPackage) {
            $match = $allPackages->findFirst(
                fn(int $key, Package $package): bool => $package->getName() === $subPackage->getName()
            );
            if ($match !== null) {
                return $match;
            }
        }

        return null;
    }

    /**
     * @param array<string, Framework> $results
     */
    private function boostWithFilesystem(string $projectPath, array &$results): void
    {
        foreach (Frameworks::getFilesystemFingerprints() as $frameworkValue => $files) {
            if (!isset($results[$frameworkValue])) {
                continue;
            }

            $bonus = $this->countFilesystemMatches($projectPath, $files) * 10;
            if ($bonus > 0) {
                $framework = $results[$frameworkValue];
                $framework->setCertainty(min(100.0, $framework->getCertainty() + $bonus));
            }
        }
    }

    /**
     * @param array<string, Framework> $results
     */
    private function detectFilesystemOnly(string $projectPath, array &$results): void
    {
        foreach (Frameworks::getFilesystemFingerprints() as $frameworkValue => $files) {
            if (isset($results[$frameworkValue])) {
                continue;
            }

            $matchCount = $this->countFilesystemMatches($projectPath, $files);
            if ($matchCount > 0) {
                $results[$frameworkValue] = new Framework(
                    name: $frameworkValue,
                    packageVersion: null,
                    frameworkTypeEnum: FrameworkTypeEnum::from($frameworkValue),
                    certainty: min(100.0, (float) ($matchCount * 30))
                );
            }
        }
    }

    /**
     * @param list<string> $files
     */
    private function countFilesystemMatches(string $projectPath, array $files): int
    {
        $count = 0;
        foreach ($files as $file) {
            if (file_exists($projectPath . DIRECTORY_SEPARATOR . $file)) {
                $count++;
            }
        }
        return $count;
    }
}
