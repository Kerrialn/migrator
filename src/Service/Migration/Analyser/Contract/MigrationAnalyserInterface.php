<?php

declare(strict_types=1);

namespace KerrialNewham\Migrator\Service\Migration\Analyser\Contract;

interface MigrationAnalyserInterface
{
    public function analyse(): float;
}
