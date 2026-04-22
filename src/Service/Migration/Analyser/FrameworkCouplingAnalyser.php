<?php

declare(strict_types=1);

namespace KerrialNewham\Migrator\Service\Migration\Analyser;

use Doctrine\Common\Collections\Collection;
use KerrialNewham\Migrator\Data\FrameworkCouplingSignatures;
use KerrialNewham\Migrator\Enum\FrameworkTypeEnum;
use KerrialNewham\Migrator\Service\Migration\Analyser\Contract\MigrationAnalyserInterface;
use Symfony\Component\Finder\SplFileInfo;

final readonly class FrameworkCouplingAnalyser implements MigrationAnalyserInterface
{
    /** @param Collection<int, SplFileInfo> $files */
    public function __construct(
        private Collection $files,
        private FrameworkTypeEnum $sourceFramework,
        private ?FrameworkTypeEnum $targetFramework = null,
    ) {
    }

    public function analyse(): float
    {
        if ($this->files->isEmpty()) {
            return 100.0;
        }

        // Coupling to the source framework is irrelevant when migrating to it
        if ($this->targetFramework !== null && $this->sourceFramework === $this->targetFramework) {
            return 100.0;
        }

        $signatures = FrameworkCouplingSignatures::getSignatures()[$this->sourceFramework->value] ?? null;
        if ($signatures === null) {
            return 100.0;
        }

        $totalFiles = $this->files->count();
        $coupledFiles = 0;
        $heavilyCoupledFiles = 0;

        foreach ($this->files as $file) {
            $references = $this->countSignatures($file->getContents(), $signatures);
            if ($references >= 1) {
                $coupledFiles++;
            }
            if ($references >= 4) {
                $heavilyCoupledFiles++;
            }
        }

        $coupledRatio = $coupledFiles / $totalFiles;
        $heavilyRatio = $heavilyCoupledFiles / $totalFiles;

        // Lightly coupled files subtract up to 60 points, heavily coupled up to 40
        $score = 100.0 - ($coupledRatio * 60.0) - ($heavilyRatio * 40.0);

        return round(max(0.0, min(100.0, $score)), 2);
    }

    /**
     * @param array{namespaces: string[], helpers: string[], facades: string[], baseClasses: string[], specificPackagePrefixes: string[]} $signatures
     */
    private function countSignatures(string $content, array $signatures): int
    {
        $count = 0;

        foreach ($signatures['namespaces'] as $namespace) {
            $count += substr_count($content, 'use ' . $namespace);
        }
        foreach ($signatures['helpers'] as $helper) {
            $count += substr_count($content, $helper);
        }
        foreach ($signatures['facades'] as $facade) {
            $count += substr_count($content, $facade);
        }
        foreach ($signatures['baseClasses'] as $baseClass) {
            $count += substr_count($content, $baseClass);
        }

        return $count;
    }
}
