<?php

namespace KerrialNewham\Migrator\NodeVisitor;

use PhpParser\Node as Node;
use PhpParser\NodeVisitorAbstract;

final class SplitClassNodeVisitor extends NodeVisitorAbstract
{
    private array $classes = [];

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Class_) {
            $this->classes[] = $node;
            return $node;
        }

        return null;
    }

    public function getClasses(): array
    {
        return $this->classes;
    }
}
