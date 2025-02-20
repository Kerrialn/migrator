<?php

namespace KerrialNewham\Migrator\Service\Calculator;

use KerrialNewham\Migrator\DataTransferObject\Project;
use KerrialNewham\Migrator\Service\Calculator\Contract\CalculatorInterface;

final readonly class MigrationCalculator implements CalculatorInterface
{
    public function calculate(Project $project) : void
    {
        var_dump('Upgrade Calculator');
    }

}
