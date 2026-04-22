<?php

declare(strict_types=1);

namespace KerrialNewham\Migrator\DataTransferObject;

use KerrialNewham\Migrator\Enum\FrameworkTypeEnum;

class Migration
{
    private null|FrameworkTypeEnum $sourceFramework = null;
    private null|FrameworkTypeEnum $targetFramework = null;
    private float $frameworkCouplingScore = 0.0;
    private float $databaseCouplingScore = 0.0;
    private bool $databaseLayerDetected = false;
    private float $dependencyCompatibilityScore = 0.0;
    private float $architectureScore = 0.0;
    private float $testCoverageScore = 0.0;
    private float $codeSizeScore = 0.0;
    private float $complexity = 0.0;
    private float $legacyCouplingScore = 0.0;
    private int $legacyFileCount = 0;

    public function getSourceFramework(): ?FrameworkTypeEnum
    {
        return $this->sourceFramework;
    }

    public function setSourceFramework(?FrameworkTypeEnum $sourceFramework): void
    {
        $this->sourceFramework = $sourceFramework;
    }

    public function getTargetFramework(): ?FrameworkTypeEnum
    {
        return $this->targetFramework;
    }

    public function setTargetFramework(?FrameworkTypeEnum $targetFramework): void
    {
        $this->targetFramework = $targetFramework;
    }

    public function getFrameworkCouplingScore(): float
    {
        return $this->frameworkCouplingScore;
    }

    public function setFrameworkCouplingScore(float $frameworkCouplingScore): void
    {
        $this->frameworkCouplingScore = $frameworkCouplingScore;
    }

    public function getDatabaseCouplingScore(): float
    {
        return $this->databaseCouplingScore;
    }

    public function setDatabaseCouplingScore(float $databaseCouplingScore): void
    {
        $this->databaseCouplingScore = $databaseCouplingScore;
    }

    public function isDatabaseLayerDetected(): bool
    {
        return $this->databaseLayerDetected;
    }

    public function setDatabaseLayerDetected(bool $databaseLayerDetected): void
    {
        $this->databaseLayerDetected = $databaseLayerDetected;
    }

    public function getDependencyCompatibilityScore(): float
    {
        return $this->dependencyCompatibilityScore;
    }

    public function setDependencyCompatibilityScore(float $dependencyCompatibilityScore): void
    {
        $this->dependencyCompatibilityScore = $dependencyCompatibilityScore;
    }

    public function getArchitectureScore(): float
    {
        return $this->architectureScore;
    }

    public function setArchitectureScore(float $architectureScore): void
    {
        $this->architectureScore = $architectureScore;
    }

    public function getTestCoverageScore(): float
    {
        return $this->testCoverageScore;
    }

    public function setTestCoverageScore(float $testCoverageScore): void
    {
        $this->testCoverageScore = $testCoverageScore;
    }

    public function getCodeSizeScore(): float
    {
        return $this->codeSizeScore;
    }

    public function setCodeSizeScore(float $codeSizeScore): void
    {
        $this->codeSizeScore = $codeSizeScore;
    }

    public function getComplexity(): float
    {
        return $this->complexity;
    }

    public function setComplexity(float $complexity): void
    {
        $this->complexity = $complexity;
    }

    public function getLegacyCouplingScore(): float
    {
        return $this->legacyCouplingScore;
    }

    public function setLegacyCouplingScore(float $legacyCouplingScore): void
    {
        $this->legacyCouplingScore = $legacyCouplingScore;
    }

    public function getLegacyFileCount(): int
    {
        return $this->legacyFileCount;
    }

    public function setLegacyFileCount(int $legacyFileCount): void
    {
        $this->legacyFileCount = $legacyFileCount;
    }

    public function hasLegacyData(): bool
    {
        return $this->legacyFileCount > 0;
    }
}
