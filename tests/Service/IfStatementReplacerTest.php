<?php

namespace Service;

use KerrialNewham\Migrator\Service\Replacer\IfStatementMissingBracketsReplacer;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class IfStatementReplacerTest extends TestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        $this->testDir = __DIR__ . '/test_files';
        mkdir($this->testDir);

        // Simple if statement without brackets
        file_put_contents($this->testDir . '/file1.php', '<?php if ($a) echo "Hello"; ?>');

        // If statement already using brackets (should remain unchanged)
        file_put_contents($this->testDir . '/file2.php', '<?php if ($b) { echo "World"; } ?>');

        // If statement with multiple lines but missing brackets
        file_put_contents($this->testDir . '/file3.php', '<?php if ($c) echo "Line1"; echo "Line2"; ?>');

        // If statement containing a foreach loop without brackets
        file_put_contents($this->testDir . '/file4.php', '<?php
            if ($condition)
                foreach ($items as $item)
                    process($item);
        ?>');
    }

    protected function tearDown(): void
    {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->testDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }

        rmdir($this->testDir);
    }

    public function testReplaceIfStatements(): void
    {
        $ifStatementReplacer = new IfStatementMissingBracketsReplacer();
        $ifStatementReplacer->replace($this->testDir);

        // Verify simple if statement is fixed
        $this->assertStringContainsString('<?php if ($a) {', file_get_contents($this->testDir . '/file1.php'));

        // Verify original brackets remain unchanged
        $this->assertStringContainsString('<?php if ($b) {', file_get_contents($this->testDir . '/file2.php'));

        // Verify multi-line if statement is wrapped properly
        $this->assertStringContainsString('<?php if ($c) {', file_get_contents($this->testDir . '/file3.php'));
        $this->assertStringContainsString('echo "Line1";', file_get_contents($this->testDir . '/file3.php'));
        $this->assertStringContainsString('echo "Line2";', file_get_contents($this->testDir . '/file3.php'));

        // Verify foreach inside if is properly wrapped
        $this->assertStringContainsString('<?php if ($condition) {', file_get_contents($this->testDir . '/file4.php'));
        $this->assertStringContainsString('foreach ($items as $item) {', file_get_contents($this->testDir . '/file4.php'));
        $this->assertStringContainsString('process($item);', file_get_contents($this->testDir . '/file4.php'));
    }
}
