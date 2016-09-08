<?php

namespace MoodleAnalyse\Parse;

use PhpParser\Node;
use PhpParser\PrettyPrinter;

/**
 * NodeVisitor for PhpParser to remove functions from code and store them.
 *
 */
class ExtractFunctionsVisitor extends \PhpParser\NodeVisitorAbstract {

    public $functions = [];

    public function beforeTraverse(array $nodes) {
        $this->functions = [];
    }

    public function leaveNode(Node $node) {
        if ($node instanceof \PhpParser\Node\Stmt\Function_) {
            $this->functions[] = $node;
            return false;
        }
    }

}
