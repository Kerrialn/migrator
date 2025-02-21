<?php

namespace KerrialNewham\Migrator\DataValueObject;

use Doctrine\Common\Collections\ArrayCollection;
use KerrialNewham\Migrator\Enum\FrameworkTypeEnum;

final readonly class FrameworkPackage
{
    /**
     * @param FrameworkTypeEnum $type
     * @param string $name
     * @param bool $isPrimary
     * @param int $weight
     * @param ArrayCollection $frameworkPackages
     */
    public function __construct(private FrameworkTypeEnum $type, private string $name, private bool $isPrimary, private int $weight, private ArrayCollection $frameworkPackages)
    {
    }

    public function getType(): FrameworkTypeEnum
    {
        return $this->type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }

    public function getWeight(): int
    {
        return $this->weight;
    }

    public function getFrameworkPackages(): ArrayCollection
    {
        return $this->frameworkPackages;
    }

}
