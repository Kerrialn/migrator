<?php

declare(strict_types=1);

namespace KerrialNewham\Migrator\Service\Migration\Analyser;

use Doctrine\Common\Collections\Collection;
use KerrialNewham\Migrator\Service\Migration\Analyser\Contract\MigrationAnalyserInterface;
use Symfony\Component\Finder\SplFileInfo;

final readonly class ArchitectureAnalyser implements MigrationAnalyserInterface
{
    /** @param Collection<int, SplFileInfo> $files */
    public function __construct(private Collection $files)
    {
    }

    public function analyse(): float
    {
        $total = $this->files->count();
        if ($total === 0) {
            return 50.0;
        }

        $serviceFiles = 0;
        $repositoryFiles = 0;
        $interfaceFiles = 0;
        $diFiles = 0;
        $ormEntityFiles = 0;

        foreach ($this->files as $file) {
            $path = strtolower($file->getPathname());
            $content = $file->getContents();

            if (str_contains($path, '/service') || str_contains($path, '/services')) {
                $serviceFiles++;
            }

            if (str_contains($path, '/repositor')) {
                $repositoryFiles++;
            }

            if ((bool) preg_match('/\binterface\s+\w+/i', $content)) {
                $interfaceFiles++;
            }

            // Constructor injection: __construct with at least one typed parameter
            if ((bool) preg_match('/public\s+function\s+__construct\s*\([^)]*[A-Z][a-zA-Z\\\\]+\s+\$/', $content)) {
                $diFiles++;
            }

            // Doctrine Data Mapper entities represent proper separation of concerns
            if (
                str_contains($content, 'Doctrine\ORM\Mapping')
                || str_contains($content, '#[ORM\\')
                || str_contains($content, '@ORM\\')
            ) {
                $ormEntityFiles++;
            }
        }

        // Service layer — up to 25 pts (10% of files in /service = full marks)
        $serviceScore = min(25.0, ($serviceFiles / $total) * 250.0);

        // Repository pattern — up to 20 pts
        $repositoryScore = min(20.0, ($repositoryFiles / $total) * 200.0);

        // Interface usage — up to 20 pts (20% of files defining interfaces = full marks)
        $interfaceScore = min(20.0, ($interfaceFiles / $total) * 100.0);

        // Constructor DI — up to 20 pts (25% of files using DI = full marks)
        $diScore = min(20.0, ($diFiles / $total) * 80.0);

        // Data Mapper ORM (Doctrine) — up to 15 pts (5% of files as entities = full marks)
        $ormScore = min(15.0, ($ormEntityFiles / $total) * 300.0);

        return round($serviceScore + $repositoryScore + $interfaceScore + $diScore + $ormScore, 2);
    }
}
