<?php

declare(strict_types=1);

namespace KerrialNewham\Migrator\Service\Replacer;


use KerrialNewham\Migrator\NodeVisitor\RemoveIfStatementsWithoutBracketsNodeVisitor;
use KerrialNewham\Migrator\Service\Replacer\Contract\ReplacerInterface;
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PhpVersion;
use PhpParser\PrettyPrinter\Standard;
use RuntimeException;
use Symfony\Component\Finder\SplFileInfo;

class IfStatementMissingBracketsReplacer implements ReplacerInterface
{
    public function replace(SplFileInfo $file) : void
    {
        $parser = (new ParserFactory())->createForVersion(PhpVersion::fromString("7.0"));
        $filePath = $file->getPathname();
        $content = $file->getContents();

        try {
            $stmts = $parser->parse($content);
        } catch (Error $e) {
            throw new RuntimeException("Failed to parse file: $filePath - " . $e->getMessage());
        }

        // Step 1: Traverse the AST to find all if statements without brackets
        $traverser = new NodeTraverser();
        $removeIfStatementsWithoutBracketsNodeVisitor = new RemoveIfStatementsWithoutBracketsNodeVisitor();
        $traverser->addVisitor($removeIfStatementsWithoutBracketsNodeVisitor);
        $traverser->traverse($stmts);

        // Step 2: Get the list of if statements without brackets
        $ifsWithoutBrackets = $removeIfStatementsWithoutBracketsNodeVisitor->getStatements();

        // Step 3: Mark collected if statements so the pretty printer normalises them with braces.
        // The Standard pretty printer always emits braces for if bodies; no AST surgery needed.
        foreach ($ifsWithoutBrackets as $ifStmt) {
            $ifStmt->setAttribute('brackets', true);
        }

        // Step 4: Use the pretty printer with minimal formatting (no extra newlines or indentation)
        $prettyPrinter = new Standard();
        $modifiedContent = $prettyPrinter->prettyPrintFile($stmts);

        // Step 5: Write the modified content back to the file
        file_put_contents($filePath, $modifiedContent);
    }

}
