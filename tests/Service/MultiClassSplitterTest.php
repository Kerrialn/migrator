<?php

namespace Test\Service;

use KerrialNewham\Migrator\Service\MultiClassSplitter\MultiClassSplitter;
use PhpParser\Node as Node;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PhpVersion;
use PhpParser\PrettyPrinter\Standard;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\SplFileInfo;
use PHPUnit\Framework\TestCase;

class MultiClassSplitterTest extends TestCase
{
    private string $tempDir;
    private Filesystem $filesystem;
    private MultiClassSplitter $splitter;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/multi_class_splitter_test';
        $this->filesystem = new Filesystem();

        if ($this->filesystem->exists($this->tempDir)) {
            $this->filesystem->remove($this->tempDir);
        }
        $this->filesystem->mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        // Cleanup after test
        if ($this->filesystem->exists($this->tempDir)) {
            $this->filesystem->remove($this->tempDir);
        }
    }

    public function testSplitMultipleClassesIntoSeparateFiles()
    {
        // Create a PHP file with multiple classes
        $filePath = $this->tempDir . '/MultipleClasses.php';
        $fileContent = <<<PHP
        <?php

        namespace TestNamespace;

        class FirstClass {
            public function sayHello() { echo "Hello from FirstClass"; }
        }

        class second_class {
            public function sayHello() { echo "Hello from second_class"; }
        }
        PHP;

        $this->filesystem->dumpFile($filePath, $fileContent);
        $parser = (new ParserFactory())->createForVersion(PhpVersion::fromString("7.0"));
        $file = new SplFileInfo($filePath, '', '');
        $this->splitter = new MultiClassSplitter($this->filesystem, $parser, $file);

        // Execute the split method
        $this->splitter->split();

        // Check that new files are created
        $firstClassFile = $this->tempDir . '/FirstClass.php';
        $secondClassFile = $this->tempDir . '/SecondClass.php';

        $this->assertFileExists($firstClassFile);
        $this->assertFileExists($secondClassFile);

        $secondFilename = basename($secondClassFile);
        $this->assertEquals('SecondClass.php', $secondFilename);


        // Check that original file is removed
        $this->assertFileDoesNotExist($filePath);

        // Verify file contents
        $firstClassContent = file_get_contents($firstClassFile);
        $secondClassContent = file_get_contents($secondClassFile);

        $this->assertStringContainsString('namespace TestNamespace;', $firstClassContent);
        $this->assertStringContainsString('class FirstClass', $firstClassContent);

        $this->assertStringContainsString('namespace TestNamespace;', $secondClassContent);
        $this->assertStringContainsString('class SecondClass', $secondClassContent);
    }




}
