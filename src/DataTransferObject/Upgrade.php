<?php

namespace KerrialNewham\Migrator\DataTransferObject;

use KerrialNewham\Migrator\Enum\PhpVersionEnum;

final class Upgrade
{
    private PhpVersionEnum $targetPhpVersion;
    private float $frameworkVersionUpgradabilityScore;
    private float $dependenciesUpgradabilityScore;
    private float $phpVersionUpgradabilityScore;
    private float $codebaseSizeUpgradabilityScore;
    private float $complexity;

    public function getTargetPhpVersion(): PhpVersionEnum
    {
        return $this->targetPhpVersion;
    }

    public function setTargetPhpVersion(PhpVersionEnum $targetPhpVersion): void
    {
        $this->targetPhpVersion = $targetPhpVersion;
    }

    public function getFrameworkVersionUpgradabilityScore(): float
    {
        return $this->frameworkVersionUpgradabilityScore;
    }

    public function setFrameworkVersionUpgradabilityScore(float $frameworkVersionUpgradabilityScore): void
    {
        $this->frameworkVersionUpgradabilityScore = $frameworkVersionUpgradabilityScore;
    }

    public function getDependenciesUpgradabilityScore(): float
    {
        return $this->dependenciesUpgradabilityScore;
    }

    public function setDependenciesUpgradabilityScore(float $dependenciesUpgradabilityScore): void
    {
        $this->dependenciesUpgradabilityScore = $dependenciesUpgradabilityScore;
    }

    public function getPhpVersionUpgradabilityScore(): float
    {
        return $this->phpVersionUpgradabilityScore;
    }

    public function setPhpVersionUpgradabilityScore(float $phpVersionUpgradabilityScore): void
    {
        $this->phpVersionUpgradabilityScore = $phpVersionUpgradabilityScore;
    }

    public function getCodebaseSizeUpgradabilityScore(): float
    {
        return $this->codebaseSizeUpgradabilityScore;
    }

    public function setCodebaseSizeUpgradabilityScore(float $codebaseSizeUpgradabilityScore): void
    {
        $this->codebaseSizeUpgradabilityScore = $codebaseSizeUpgradabilityScore;
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
