<?php

declare(strict_types=1);

namespace KerrialNewham\Migrator\Service\Migration\Analyser;

use Doctrine\Common\Collections\Collection;
use KerrialNewham\Migrator\Service\Migration\Analyser\Contract\MigrationAnalyserInterface;
use Symfony\Component\Finder\SplFileInfo;

final readonly class TestCoverageAnalyser implements MigrationAnalyserInterface
{
    /** @param Collection<int, SplFileInfo> $files */
    public function __construct(private Collection $files)
    {
    }

    public function analyse(): float
    {
        $total = $this->files->count();
        if ($total === 0) {
            return 0.0;
        }

        $testFiles = 0;
        $frameworkCoupledTests = 0;

        foreach ($this->files as $file) {
            $path = strtolower($file->getPathname());

            if (!$this->isTestFile($path)) {
                continue;
            }

            $testFiles++;
            $content = $file->getContents();

            if ($this->isFrameworkCoupledTest($content)) {
                $frameworkCoupledTests++;
            }
        }

        if ($testFiles === 0) {
            return 0.0;
        }

        $testRatio = $testFiles / $total;

        // Coverage ratio contributes up to 70 pts (50% test/source ratio = full 70)
        $coverageScore = min(70.0, $testRatio * 140.0);

        // Framework-independent tests add up to 30 pts
        $independentRatio = ($testFiles - $frameworkCoupledTests) / $testFiles;
        $independentScore = $independentRatio * 30.0;

        return round(min(100.0, $coverageScore + $independentScore), 2);
    }

    private function isTestFile(string $path): bool
    {
        return str_contains($path, '/test') || str_ends_with($path, 'test.php');
    }

    private function isFrameworkCoupledTest(string $content): bool
    {
        return str_contains($content, 'Illuminate\Foundation\Testing')
            || str_contains($content, 'Tests\TestCase')
            || str_contains($content, 'Symfony\Bundle\FrameworkBundle\Test')
            || str_contains($content, 'extends TestCase') && str_contains($content, 'CreatesApplication');
    }
}
