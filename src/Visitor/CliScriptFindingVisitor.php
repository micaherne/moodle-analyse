<?php

namespace MoodleAnalyse\Visitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * Visitor to look for define('CLI_SCRIPT') call.
 */
class CliScriptFindingVisitor extends NodeVisitorAbstract
{

    private bool $isCliScript = false;

    public function isCliScript()
    {
        return $this->isCliScript;
    }

    public function beforeTraverse(array $nodes)
    {
        $this->isCliScript = false;
    }

    public function enterNode(Node $node)
    {
        if ($this->isCliScript) {
            return;
        }

        if (!$node instanceof Node\Stmt\Expression) {
            return;
        }

        if (!$node->expr instanceof Node\Expr\FuncCall) {
            return;
        }

        if ($node->expr->name->toString() !== 'define') {
            return;
        }

        // define() has two mandatory arguments, so we just assume that they are there.

        if ($node->expr->args[0]->value->value !== 'CLI_SCRIPT') {
            return;
        }

        // CLI_SCRIPT can actually have any truthy value in Moodle but we assume it's 1 or true and not something stupid.

        if ($node->expr->args[1]->value instanceof Node\Scalar\LNumber && $node->expr->args[1]->value->value === 1) {
            $this->isCliScript = true;
            return;
        }

        if ($node->expr->args[1]->value instanceof Node\Expr\ConstFetch && $node->expr->args[1]->value->name->toString() === 'true') {
            $this->isCliScript = true;
            return;
        }

    }




}