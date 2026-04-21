<?php

namespace KerrialNewham\Migrator\NodeVisitor;

use PhpParser\Node as Node;
use PhpParser\NodeVisitorAbstract;

final class RemoveIfStatementsWithoutBracketsNodeVisitor extends NodeVisitorAbstract
{
    /** @var Node\Stmt\If_[] */
    private array $statements = [];

    public function enterNode(Node $node): Node|null
    {
        if ($node instanceof Node\Stmt\If_) {
            if (count($node->stmts) === 1 && !$node->hasAttribute('brackets')) {
                $this->statements[] = $node;
            }

            return $node;
        }

        return null;
    }

    /** @return Node\Stmt\If_[] */
    public function getStatements(): array
    {
        return $this->statements;
    }
}
