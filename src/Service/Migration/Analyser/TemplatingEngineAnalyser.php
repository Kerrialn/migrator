<?php

declare(strict_types=1);

namespace KerrialNewham\Migrator\Service\Migration\Analyser;

use KerrialNewham\ComposerJsonParser\Model\Composer;
use KerrialNewham\Migrator\Enum\FrameworkTypeEnum;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

final readonly class TemplatingEngineAnalyser
{
    /** @param string[] $exclude */
    public function __construct(
        private string $path,
        private array $exclude,
        private FrameworkTypeEnum $targetFramework,
        private ?Composer $composer = null,
    ) {
    }

    /** @return SplFileInfo[] */
    private function findTemplateFiles(): array
    {
        $files = [];

        // Non-PHP template files: Twig, Blade, Volt, Smarty, phtml
        try {
            $finder = (new Finder())
                ->in($this->path)
                ->exclude($this->exclude)
                ->files()
                ->name(['*.twig', '*.blade.php', '*.volt', '*.tpl', '*.phtml']);
            foreach ($finder as $file) {
                $files[] = $file;
            }
        } catch (\InvalidArgumentException) {
        }

        // PHP files inside common view directories
        try {
            $viewFinder = (new Finder())
                ->in($this->path)
                ->exclude($this->exclude)
                ->files()
                ->name('*.php')
                ->path('/\/(views?|templates?|pages?|partials?|layouts?)\//i');
            foreach ($viewFinder as $file) {
                $files[] = $file;
            }
        } catch (\InvalidArgumentException) {
        }

        return $files;
    }

    public function detectEngine(): string
    {
        $files = $this->findTemplateFiles();

        foreach ($files as $file) {
            $name = $file->getPathname();
            if (str_ends_with($name, '.blade.php')) {
                return 'blade';
            }
            if (str_ends_with($name, '.volt')) {
                return 'volt';
            }
            if (str_ends_with($name, '.twig')) {
                return 'twig';
            }
            if (str_ends_with($name, '.tpl')) {
                return 'smarty';
            }
        }

        if ($this->composer !== null) {
            foreach ($this->composer->getRequire() as $package) {
                if (str_starts_with($package->getName(), 'twig/')) {
                    return 'twig';
                }
                if (str_starts_with($package->getName(), 'smarty/')) {
                    return 'smarty';
                }
            }
        }

        if ($files !== []) {
            return 'php';
        }

        return 'none';
    }

    public function hasTemplates(): bool
    {
        return $this->detectEngine() !== 'none';
    }

    public function getTemplateFileCount(): int
    {
        return count($this->findTemplateFiles());
    }

    public function analyse(): float
    {
        $engine = $this->detectEngine();

        return match ($engine) {
            'twig'   => $this->scoreTwig(),
            'blade'  => $this->scoreBlade(),
            'volt'   => 20.0,
            'smarty' => 50.0,
            'php'    => $this->scorePhpTemplates(),
            default  => 100.0,
        };
    }

    private function scoreTwig(): float
    {
        return match ($this->targetFramework) {
            FrameworkTypeEnum::SYMFONY                              => 95.0,
            FrameworkTypeEnum::LARAVEL, FrameworkTypeEnum::TEMPEST => 70.0,
            default                                                 => 60.0,
        };
    }

    private function scoreBlade(): float
    {
        return $this->targetFramework === FrameworkTypeEnum::LARAVEL ? 95.0 : 25.0;
    }

    private function scorePhpTemplates(): float
    {
        $files = $this->findTemplateFiles();
        if ($files === []) {
            return 100.0;
        }

        $spaghettiScores = array_map(
            fn(SplFileInfo $file): float => $this->spaghettiScore($file->getContents()),
            $files
        );

        $avgSpaghetti = array_sum($spaghettiScores) / count($spaghettiScores);

        // Clean PHP templates → 70, heavy spaghetti → 15
        return round(max(15.0, 70.0 - ($avgSpaghetti * 55.0)), 2);
    }

    private function spaghettiScore(string $content): float
    {
        $signals = 0;

        // PHP/HTML interleaving: count close-tag switches, capped at 5
        $signals += min(5, substr_count($content, '?>'));

        // Direct superglobal access in templates
        if (str_contains($content, '$_GET') || str_contains($content, '$_POST')) {
            $signals += 3;
        }
        if (str_contains($content, '$_SESSION')) {
            $signals += 2;
        }

        // Business logic leaking in
        if ((bool) preg_match('/\bnew\s+[A-Z]/', $content)) {
            $signals += 2;
        }
        if (str_contains($content, 'require') || str_contains($content, 'include ')) {
            $signals += 1;
        }

        // Database access in templates — worst case
        if (
            str_contains($content, '$pdo->') || str_contains($content, 'mysqli_')
            || str_contains($content, '$this->db->') || str_contains($content, '->query(')
        ) {
            $signals += 5;
        }

        // File length penalty
        $lines = substr_count($content, "\n") + 1;
        if ($lines > 200) {
            $signals += 2;
        }
        if ($lines > 500) {
            $signals += 3;
        }

        return min(1.0, $signals / 20.0);
    }
}
