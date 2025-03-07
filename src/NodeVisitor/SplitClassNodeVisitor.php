<?php

namespace KerrialNewham\Migrator\Service\MultiClassSplitter\NodeVisitor;

use PhpParser\NodeVisitorAbstract;
use PhpParser\Node as Node;

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
