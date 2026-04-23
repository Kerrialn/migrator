<?php

declare(strict_types=1);

namespace KerrialNewham\Migrator\Service\Migration\Analyser;

use KerrialNewham\ComposerJsonParser\Model\Composer;
use KerrialNewham\ComposerJsonParser\Model\Package;
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
            $isTargetCompatible = $targetPrefixes !== [] && $this->isFrameworkSpecific($package->getName(), $targetPrefixes);

            if ($isSourceSpecific && !$isTargetCompatible) {
                $frameworkSpecificCount++;
            }
        }

        $frameworkScore = (1 - $frameworkSpecificCount / $total) * 100.0;
        $phpPenalty = $this->calculatePhpVersionPenalty();

        return round(max(0.0, min(100.0, $frameworkScore - $phpPenalty)), 2);
    }

    private function calculatePhpVersionPenalty(): float
    {
        if ($this->targetFramework === null || $this->composer === null) {
            return 0.0;
        }

        $targetMinPhp = $this->getTargetFrameworkMinPhp($this->targetFramework);
        if ($targetMinPhp === null) {
            return 0.0;
        }

        $phpPackage = $this->composer->getRequire()->findFirst(
            fn(int $key, Package $p): bool => str_contains($p->getName(), 'php')
        );

        if ($phpPackage === null || $phpPackage->getPackageVersion() === null) {
            return 0.0;
        }

        $versionString = preg_replace('/[^0-9.]/', '', $phpPackage->getPackageVersion()->getVersionString()) ?? '';
        $parts = explode('.', $versionString);
        $sourceMajor = (int) ($parts[0] ?? 0);
        $sourceMinor = (int) ($parts[1] ?? 0);

        [$targetMajor, $targetMinor] = $targetMinPhp;

        $majorGap = max(0, $targetMajor - $sourceMajor);
        if ($majorGap === 0) {
            $minorGap = max(0, $targetMinor - $sourceMinor);
            return min(30.0, $minorGap * 5.0);
        }

        // Crossing a major PHP boundary: significant portion of packages will have moved on
        return min(50.0, ($majorGap * 25.0) + ($targetMinor * 5.0));
    }

    /**
     * Minimum PHP version required by the latest stable major of each target framework.
     * @return array{int, int}|null
     */
    private function getTargetFrameworkMinPhp(FrameworkTypeEnum $framework): ?array
    {
        return match ($framework) {
            FrameworkTypeEnum::SYMFONY     => [8, 2],
            FrameworkTypeEnum::LARAVEL     => [8, 2],
            FrameworkTypeEnum::TEMPEST     => [8, 2],
            FrameworkTypeEnum::CAKEPHP     => [8, 1],
            FrameworkTypeEnum::ZEND        => [8, 1],
            FrameworkTypeEnum::YII         => [8, 1],
            FrameworkTypeEnum::CODEIGNITER => [8, 1],
            FrameworkTypeEnum::PHALCON     => [8, 0],
            default                        => null,
        };
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
