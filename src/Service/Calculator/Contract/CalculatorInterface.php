<?php

namespace KerrialNewham\Migrator\Service\Calculator\Contract;

use KerrialNewham\Migrator\DataTransferObject\Project;
use Symfony\Component\Console\Style\SymfonyStyle;

interface CalculatorInterface
{
    public function calculate(Project $project, SymfonyStyle $io) : void;
}
