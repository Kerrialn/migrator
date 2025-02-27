<?php

declare(strict_types=1);

namespace Test\Service;

use KerrialNewham\Migrator\Service\Replacer\ShortTagReplacer;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ShortTagReplacerTest extends TestCase
{
    private string $testDirectoryPath;

    protected function setUp(): void
    {
        $this->testDirectoryPath = __DIR__ . '/test_files';
        mkdir($this->testDirectoryPath);
        file_put_contents($this->testDirectoryPath . '/file1.php', '<? echo "Hello"; ?>');
        file_put_contents($this->testDirectoryPath . '/file2.php', '<?php echo "Hello"; ?>');
        file_put_contents($this->testDirectoryPath . '/file3.php', '<? echo "World"; ?>');
    }

    protected function tearDown(): void
    {
        // Clean up after the test
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->testDirectoryPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }

        rmdir($this->testDirectoryPath); // Remove the test directory
    }

    public function testReplaceShortTags(): void
    {
        $shortTagReplacer = new ShortTagReplacer();
        $shortTagReplacer->replace($this->testDirectoryPath);

        $this->assertStringContainsString('<?php', file_get_contents($this->testDirectoryPath . '/file1.php'));
        $this->assertStringContainsString('<?php', file_get_contents($this->testDirectoryPath . '/file3.php'));
        $this->assertStringContainsString('<?php', file_get_contents($this->testDirectoryPath . '/file2.php'));
    }
}
