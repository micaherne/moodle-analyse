<?php

namespace MoodleAnalyse\Parse;

use PhpParser\Node;
use PhpParser\PrettyPrinter;

/**
 * NodeVisitor for PhpParser to rewrite requires etc. to absolute Moodle paths.
 *
 */
class RequireResolverVisitor extends \PhpParser\NodeVisitorAbstract {

    private $relativeDir;
    private $removeConfigIncludes;

    public function __construct($relativeDir = '', $removeConfigIncludes = true) {
        $this->relativeDir = $relativeDir;
        $this->removeConfigIncludes = $removeConfigIncludes;
        $this->prettyPrinter = new PrettyPrinter\Standard();
    }

    public function leaveNode(Node $node) {
        if ($node instanceof \PhpParser\Node\Expr\Include_) {
            $expr = $node->expr;

            // Check if the require has config.php in it *anywhere* and remove
            // it if it does. This is hackier, but easier, than trying to work
            // out all the possible combinations of concats.
            $exprcode = $this->prettyPrinter->prettyPrint([$node->expr]);
            if ($this->removeConfigIncludes && preg_match('/\bconfig.php\b/', $exprcode)) {
                return false; // Remove the node
            // TODO: We should look for other ways of requiring a file without
            // using $CFG here (e.g. __DIR__, dirname() etc).
            } else if ($expr instanceof \PhpParser\Node\Scalar\String_) {
                // Replace node with a fixed require node.
                $newnode = $this->buildRequireNode($expr->value, $node->type);
                return [$newnode];
            } else if (preg_match('/(dirname|__DIR__)/', $exprcode)) {
                throw new \Exception("Not supported format: " . $exprcode);
            }

            /*
             * This is maybe more like how it should be done...
             *
            if ($expr instanceof \PhpParser\Node\Scalar\String_) {
                // This will remove any requires to files called config.php
                if (preg_match('/\bconfig.php\b/', $expr->value)) {
                    return false; // Remove the node
                } else {
                    // Replace node with a fixed require node.
                    $newnode = $this->buildRequireNode($expr->value, $node->type);
                    return [$newnode];
                }
            }
            */
        }
    }

    private function buildRequireNode($relativePath, $includeType) {
        $relativeToRootPath = $this->relativeDir . '/' . $relativePath;
        $expr = new \PhpParser\Node\Expr\BinaryOp\Concat(
            new \PhpParser\Node\Expr\PropertyFetch(
                 new \PhpParser\Node\Expr\Variable('CFG'),
                 'dirroot'
            ),
            new \PhpParser\Node\Scalar\String_($relativeToRootPath)
        );
        return new \PhpParser\Node\Expr\Include_($expr, $includeType);
    }

}
