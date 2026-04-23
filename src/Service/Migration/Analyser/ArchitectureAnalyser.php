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

            // Service layer: path-based (Symfony/Laravel) OR Phalcon Injectable
            if (
                str_contains($path, '/service') || str_contains($path, '/services')
                || str_contains($content, 'Phalcon\Di\Injectable')
                || (bool) preg_match('/\bextends\s+Injectable\b/', $content)
            ) {
                $serviceFiles++;
            }

            if (str_contains($path, '/repositor')) {
                $repositoryFiles++;
            }

            if ((bool) preg_match('/\binterface\s+\w+/i', $content)) {
                $interfaceFiles++;
            }

            // Constructor injection (PSR/Symfony/Laravel) OR Phalcon service locator DI
            if (
                (bool) preg_match('/public\s+function\s+__construct\s*\([^)]*[A-Z][a-zA-Z\\\\]+\s+\$/', $content)
                || str_contains($content, '->getDI()')
                || str_contains($content, 'getDI()->get(')
            ) {
                $diFiles++;
            }

            // Doctrine entities OR Phalcon ORM models (may extend a custom base that wraps Phalcon\Mvc\Model)
            if (
                str_contains($content, 'Doctrine\ORM\Mapping')
                || str_contains($content, '#[ORM\\')
                || str_contains($content, '@ORM\\')
                || str_contains($content, 'Phalcon\Mvc\Model')
                || (str_contains($path, '/model') && (bool) preg_match('/\bextends\s+\w*Model\b/', $content))
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

        // Constructor DI or framework-managed DI — up to 20 pts (25% of files = full marks)
        $diScore = min(20.0, ($diFiles / $total) * 80.0);

        // Data Mapper ORM or framework ORM models — up to 15 pts (5% of files = full marks)
        $ormScore = min(15.0, ($ormEntityFiles / $total) * 300.0);

        return round($serviceScore + $repositoryScore + $interfaceScore + $diScore + $ormScore, 2);
    }
}
