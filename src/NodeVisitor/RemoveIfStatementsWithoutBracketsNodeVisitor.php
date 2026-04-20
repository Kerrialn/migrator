<?php

namespace KerrialNewham\Migrator\NodeVisitor;

use PhpParser\Node as Node;
use PhpParser\NodeVisitorAbstract;

final class RemoveIfStatementsWithoutBracketsNodeVisitor extends NodeVisitorAbstract
{
    private array $statements = [];

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\If_) {
            // If the statement has no braces and has only one statement inside
            if (count($node->stmts) === 1 && !$node->hasAttribute('brackets')) {
                // Wrap the single statement with a block (adding curly braces)
                $this->statements[] = $node;
            }

            return $node;
        }

        return null;
    }

    public function getStatements(): array
    {
        return $this->statements;
    }
}
