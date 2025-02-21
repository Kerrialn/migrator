<?php

namespace KerrialNewham\Migrator\DataValueObject;

use KerrialNewham\ComposerJsonParser\Model\PackageVersion;
use KerrialNewham\Migrator\Enum\FrameworkTypeEnum;

final class Framework
{
    private null|float $daysUntilEndOfLife = null;

    /**
     * @param string $name
     * @param PackageVersion $packageVersion
     * @param FrameworkTypeEnum $frameworkTypeEnum
     * @param float|null $certainty
     */
    public function __construct(
        private readonly string $name,
        private readonly PackageVersion $packageVersion,
        private FrameworkTypeEnum $frameworkTypeEnum,
        private null|float $certainty = null)
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getFrameworkTypeEnum(): FrameworkTypeEnum
    {
        return $this->frameworkTypeEnum;
    }

    public function setFrameworkTypeEnum(FrameworkTypeEnum $frameworkTypeEnum): void
    {
        $this->frameworkTypeEnum = $frameworkTypeEnum;
    }

    public function getCertainty(): ?float
    {
        return $this->certainty;
    }

    public function setCertainty(?float $certainty): void
    {
        $this->certainty = $certainty;
    }

    public function getDaysUntilEndOfLife(): ?float
    {
        return $this->daysUntilEndOfLife;
    }

    public function setDaysUntilEndOfLife(?float $daysUntilEndOfLife): void
    {
        $this->daysUntilEndOfLife = $daysUntilEndOfLife;
    }

    public function getPackageVersion(): PackageVersion
    {
        return $this->packageVersion;
    }

}
