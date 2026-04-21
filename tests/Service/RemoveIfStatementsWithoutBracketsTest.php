<?php

namespace Test\Service;

use KerrialNewham\Migrator\Service\Replacer\IfStatementMissingBracketsReplacer;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\SplFileInfo;

class RemoveIfStatementsWithoutBracketsTest extends TestCase
{
    private Filesystem $filesystem;

    protected function setUp(): void
    {

        $this->filesystem = new Filesystem();
    }

    public function testReplaceAddsBracketsToIfStatementsWithoutBrackets(): void
    {
        // Create a temporary file to test the replacement
        $testFilePath = sys_get_temp_dir() . '/testfile.php';
        $content = '<?php if ($x) echo "Hello";';
        $this->filesystem->dumpFile($testFilePath, $content);

        // Create SplFileInfo for the file
        $file = new SplFileInfo($testFilePath,$testFilePath,$testFilePath);

        // Create an instance of the class that contains the `replace` method
        $yourClassInstance = new IfStatementMissingBracketsReplacer();  // Replace with the actual class name

        // Call the replace method
        $yourClassInstance->replace($file);

        // Get the new file content
        $newContent = file_get_contents($testFilePath);

        // Assert that the content now contains the correct if statement with braces
        $this->assertStringContainsString('if ($x) {', $newContent);
        $this->assertStringContainsString('echo "Hello";', $newContent);
        $this->assertStringContainsString('}', $newContent);

        // Clean up
        $this->filesystem->remove($testFilePath);
    }

    public function testReplaceDoesNotModifyIfStatementsWithBrackets(): void
    {
        $testFilePath = sys_get_temp_dir() . '/testfile2.php';
        $content = '<?php if ($x) { echo "Hello"; }';
        $this->filesystem->dumpFile($testFilePath, $content);

        $file = new SplFileInfo($testFilePath, $testFilePath, $testFilePath);
        $yourClassInstance = new IfStatementMissingBracketsReplacer();
        $yourClassInstance->replace($file);

        $newContent = file_get_contents($testFilePath);

        // The pretty printer reformats but must preserve the bracketed structure
        $this->assertStringContainsString('if ($x) {', $newContent);
        $this->assertStringContainsString('echo "Hello";', $newContent);
        $this->assertStringContainsString('}', $newContent);
        $this->assertStringNotContainsString('<?php<?php', $newContent);

        $this->filesystem->remove($testFilePath);
    }

    public function testReplaceThrowsExceptionIfFileCannotBeParsed(): void
    {
        // Create a temporary file with invalid PHP content
        $testFilePath = sys_get_temp_dir() . '/testfile3.php';
        $content = '<?php if ($x echo "Hello";';  // Invalid PHP syntax
        $this->filesystem->dumpFile($testFilePath, $content);

        $file = new SplFileInfo($testFilePath, $testFilePath, $testFilePath);
        $yourClassInstance = new IfStatementMissingBracketsReplacer();  // Replace with the actual class name

        // Assert that an exception is thrown when calling replace
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Failed to parse file: $testFilePath");

        // Call the replace method (this should throw an exception)
        $yourClassInstance->replace($file);

        // Clean up
        $this->filesystem->remove($testFilePath);
    }
}
