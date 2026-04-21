<?php

namespace KerrialNewham\Migrator\NodeVisitor;

use PhpParser\Node as Node;
use PhpParser\NodeVisitorAbstract;

final class SplitClassNodeVisitor extends NodeVisitorAbstract
{
    /** @var Node\Stmt\Class_[] */
    private array $classes = [];

    public function enterNode(Node $node): Node|null
    {
        if ($node instanceof Node\Stmt\Class_) {
            $this->classes[] = $node;
            return $node;
        }

        return null;
    }

    /** @return Node\Stmt\Class_[] */
    public function getClasses(): array
    {
        return $this->classes;
    }
}
