<?php

namespace KerrialNewham\Migrator\Service\Calculator;

use KerrialNewham\Migrator\DataTransferObject\Project;
use KerrialNewham\Migrator\Service\Calculator\Contract\CalculatorInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final readonly class MigrationCalculator implements CalculatorInterface
{
    public function calculate(Project $project, SymfonyStyle $io) : void
    {
    }

}
