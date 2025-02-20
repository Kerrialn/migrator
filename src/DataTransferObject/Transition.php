<?php

namespace KerrialNewham\Migrator\DataTransferObject;

use KerrialNewham\Migrator\Enum\TransitionTypeEnum;

final class Transition
{

    private null|float $complexity = null;
    private null|TransitionTypeEnum $transitionTypeEnum = null;

    public function getComplexity(): ?float
    {
        return $this->complexity;
    }

    public function setComplexity(?float $complexity): void
    {
        $this->complexity = $complexity;
    }

    public function getTransitionTypeEnum(): ?TransitionTypeEnum
    {
        return $this->transitionTypeEnum;
    }

    public function setTransitionTypeEnum(?TransitionTypeEnum $transitionTypeEnum): void
    {
        $this->transitionTypeEnum = $transitionTypeEnum;
    }

}
