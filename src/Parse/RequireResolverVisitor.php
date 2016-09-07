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
    private $inInclude = false;

    public function __construct($relativeDir = '', $removeConfigIncludes = true) {
        $this->relativeDir = $relativeDir;
        $this->removeConfigIncludes = $removeConfigIncludes;
        $this->prettyPrinter = new PrettyPrinter\Standard();
    }

    public function beforeTraverse(array $nodes) {
        $this->inInclude = false;
    }

    public function enterNode(Node $node) {
        if ($node instanceof \PhpParser\Node\Expr\Include_) {
            $this->inInclude = true;
        }
    }

    public function leaveNode(Node $node) {

        // Replace __DIR__ with $CFG based absolute dir.
        if ($this->inInclude && $node instanceof \PhpParser\Node\Scalar\MagicConst\Dir) {
            $newnode = $this->buildConfigAbsoluteNode($this->relativeDir);
            return $newnode;
        }

        if ($node instanceof \PhpParser\Node\Expr\Include_) {

            $this->inInclude = false;

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

    public function buildConfigAbsoluteNode($relativeToRootPath) {
        return new \PhpParser\Node\Expr\BinaryOp\Concat(
            new \PhpParser\Node\Expr\PropertyFetch(
                 new \PhpParser\Node\Expr\Variable('CFG'),
                 'dirroot'
            ),
            new \PhpParser\Node\Scalar\String_($relativeToRootPath)
        );
    }

    private function buildRequireNode($relativePath, $includeType) {
        $relativeToRootPath = $this->relativeDir . '/' . $relativePath;
        $expr = $this->buildConfigAbsoluteNode($relativeToRootPath);
        return new \PhpParser\Node\Expr\Include_($expr, $includeType);
    }

}
