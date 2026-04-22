<?php

declare(strict_types=1);

namespace KerrialNewham\Migrator\Service\Migration\Analyser;

use KerrialNewham\ComposerJsonParser\Model\Composer;
use KerrialNewham\Migrator\Data\FrameworkCouplingSignatures;
use KerrialNewham\Migrator\Enum\FrameworkTypeEnum;
use KerrialNewham\Migrator\Service\Migration\Analyser\Contract\MigrationAnalyserInterface;

final readonly class DependencyCompatibilityAnalyser implements MigrationAnalyserInterface
{
    public function __construct(
        private ?Composer $composer,
        private FrameworkTypeEnum $sourceFramework,
        private ?FrameworkTypeEnum $targetFramework = null,
    ) {
    }

    public function analyse(): float
    {
        if ($this->composer === null) {
            return 50.0;
        }

        $allPackages = [
            ...$this->composer->getRequire()->toArray(),
            ...$this->composer->getRequireDev()->toArray(),
        ];

        $total = count($allPackages);
        if ($total === 0) {
            return 100.0;
        }

        $sourcePrefixes = FrameworkCouplingSignatures::getSignatures()[$this->sourceFramework->value]['specificPackagePrefixes'] ?? [];
        $targetPrefixes = $this->targetFramework !== null
            ? (FrameworkCouplingSignatures::getSignatures()[$this->targetFramework->value]['specificPackagePrefixes'] ?? [])
            : [];

        $frameworkSpecificCount = 0;

        foreach ($allPackages as $package) {
            $isSourceSpecific = $this->isFrameworkSpecific($package->getName(), $sourcePrefixes);
            // Packages already compatible with the target are not a migration burden
            $isTargetCompatible = $targetPrefixes !== [] && $this->isFrameworkSpecific($package->getName(), $targetPrefixes);

            if ($isSourceSpecific && !$isTargetCompatible) {
                $frameworkSpecificCount++;
            }
        }

        $score = (1 - $frameworkSpecificCount / $total) * 100.0;

        return round(max(0.0, min(100.0, $score)), 2);
    }

    /** @param string[] $prefixes */
    private function isFrameworkSpecific(string $packageName, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($packageName, $prefix)) {
                return true;
            }
        }
        return false;
    }
}
