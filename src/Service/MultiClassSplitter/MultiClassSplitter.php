<?php

namespace KerrialNewham\Migrator\Service\MultiClassSplitter;

use KerrialNewham\Migrator\Helper\Strings;
use KerrialNewham\Migrator\NodeVisitor\SplitClassNodeVisitor;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\PrettyPrinter\Standard;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\SplFileInfo;

final readonly class MultiClassSplitter
{
    public function __construct(
        private Filesystem  $filesystem,
        private Parser      $parser,
        private SplFileInfo $file,
    )
    {
    }

    public function countMultiClassFiles() : int
    {
        $filePath = $this->file->getPathname();
        $content = $this->file->getContents();

        try {
            $stmts = $this->parser->parse($content);
        } catch (Error $e) {
            throw new RuntimeException("Failed to parse file: $filePath - " . $e->getMessage());
        }

        $traverser = new NodeTraverser();
        $classSplitter = new SplitClassNodeVisitor();
        $traverser->addVisitor($classSplitter);
        $traverser->traverse($stmts);

        return count($classSplitter->getClasses());
    }

    public function split(): void
    {
        $prettyPrinter = new Standard();
        $filePath = $this->file->getPathname();
        $content = $this->file->getContents();

        try {
            $stmts = $this->parser->parse($content);
        } catch (Error $e) {
            throw new RuntimeException("Failed to parse file: $filePath - " . $e->getMessage());
        }

        $traverser = new NodeTraverser();
        $classSplitter = new SplitClassNodeVisitor();
        $traverser->addVisitor($classSplitter);
        $traverser->traverse($stmts);

        $classNodes = $classSplitter->getClasses();

        if (count($classNodes) <= 1) {
            return;
        }

        $namespace = null;
        $namespaceNode = null;
        foreach ($stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\Namespace_) {
                $namespaceNode = $stmt;
                $namespace = $namespaceNode->name ? $namespaceNode->name->toString() : null;
                break;
            }
        }

        foreach ($classNodes as $classNode) {
            $className = $classNode->name->toString();
            $updatedClassName = ucfirst(Strings::toCamelCase($className));  // Convert snake_case to PascalCase (FirstClass)
            $classNode->name = new Node\Identifier($updatedClassName);

            $newStmts = [];

            if ($namespace) {
                $newStmts[] = new Node\Stmt\Namespace_(new Node\Name($namespace));
            }

            $newStmts[] = $classNode;
            $classContent = "<?php\n\n" . $prettyPrinter->prettyPrintFile($newStmts);
            $newFilePath = dirname($filePath) . DIRECTORY_SEPARATOR . $updatedClassName . '.php';
            var_dump($newFilePath);

            if (!$this->filesystem->exists($newFilePath)) {
                $this->filesystem->dumpFile($newFilePath, $classContent);
            }
        }

        // Remove the original file
        $this->filesystem->remove($filePath);
    }

}
