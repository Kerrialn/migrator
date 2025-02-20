<?php

namespace KerrialNewham\Migrator\Service\Calculator\Contract;

use KerrialNewham\Migrator\DataTransferObject\Project;

interface CalculatorInterface
{
    public function calculate(Project $project) : void;
}
