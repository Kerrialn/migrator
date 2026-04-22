<?php

declare(strict_types=1);

namespace KerrialNewham\Migrator\Service\Migration\Analyser;

use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Finder\SplFileInfo;

final readonly class CodebaseSizeAnalyser
{
    /** @param ArrayCollection<int, SplFileInfo> $files */
    public function __construct(private ArrayCollection $files) {}

    public function analyse(): float
    {
        $total = $this->files->count();
        if ($total === 0) {
            return 100.0;
        }

        return round(max(5.0, min(100.0, 200.0 - 45.0 * log10((float) $total))), 2);
    }
}
