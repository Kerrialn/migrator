<?php

declare(strict_types=1);

namespace KerrialNewham\Migrator\DataTransferObject;

use KerrialNewham\Migrator\Enum\FrameworkTypeEnum;

class Migration
{
    private null|FrameworkTypeEnum $targetFramework = null;
    private float $frameworkCouplingScore = 0.0;
    private float $ormCouplingScore = 0.0;
    private float $dependencyCompatibilityScore = 0.0;
    private float $architectureScore = 0.0;
    private float $testCoverageScore = 0.0;
    private float $complexity = 0.0;

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

    public function getOrmCouplingScore(): float
    {
        return $this->ormCouplingScore;
    }

    public function setOrmCouplingScore(float $ormCouplingScore): void
    {
        $this->ormCouplingScore = $ormCouplingScore;
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

    public function getComplexity(): float
    {
        return $this->complexity;
    }

    public function setComplexity(float $complexity): void
    {
        $this->complexity = $complexity;
    }
}
