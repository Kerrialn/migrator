<?php

namespace KerrialNewham\Migrator\DataTransferObject;

use KerrialNewham\Migrator\Enum\FrameworkTypeEnum;

class Migration
{
    private null|FrameworkTypeEnum $targetFramework = null;

    public function getTargetFramework(): ?FrameworkTypeEnum
    {
        return $this->targetFramework;
    }

    public function setTargetFramework(?FrameworkTypeEnum $targetFramework): void
    {
        $this->targetFramework = $targetFramework;
    }

}
