<?php

namespace MoodleAnalyse\Parse;

use PhpParser\Node;
use PhpParser\PrettyPrinter;

/**
 * NodeVisitor for PhpParser to remove classes from code and store them.
 *
 */
class ExtractClassesVisitor extends \PhpParser\NodeVisitorAbstract {

    public $classes = [];

    public function beforeTraverse(array $nodes) {
        $this->classes = [];
    }

    public function leaveNode(Node $node) {
        if ($node instanceof \PhpParser\Node\Stmt\Class_) {
            $this->classes[] = $node;
            return false;
        }
    }

}
