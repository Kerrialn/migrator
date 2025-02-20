<?php

namespace KerrialNewham\Migrator\DataValueObject;

use KerrialNewham\Migrator\Enum\FrameworkTypeEnum;

final class Framework
{
    private string $name;
    private FrameworkTypeEnum $frameworkTypeEnum;
    private null|float $certainty = null;

    private null|float $daysUntilEndOfLife = null;

    /**
     * @param string $name
     * @param FrameworkTypeEnum $frameworkTypeEnum
     * @param float|null $certainty
     */
    public function __construct(
        string            $name,
        FrameworkTypeEnum $frameworkTypeEnum,
        null|float        $certainty = null
    )
    {
        $this->name = $name;
        $this->frameworkTypeEnum = $frameworkTypeEnum;
        $this->certainty = $certainty;
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


}
