<?php

namespace MoodleAnalyse\Visitor;

use PhpParser\Node;

class Util
{

    public static function isMoodleInternalCheck(Node $node): bool
    {
        // Exit_ is near the start to short circuit as much as possible.
        return
            ($node instanceof Node\Expr\BinaryOp\BooleanOr
                || $node instanceof Node\Expr\BinaryOp\LogicalOr)
            && $node->right instanceof Node\Expr\Exit_
            && $node->left instanceof Node\Expr\FuncCall
            && $node->left->name instanceof Node\Name
            && $node->left->name->getParts()[0] == 'defined'
            && $node->left->args[0] instanceof Node\Arg
            && $node->left->args[0]->value instanceof Node\Scalar\String_
            && $node->left->args[0]->value->value == 'MOODLE_INTERNAL';
    }

    public static function isCliScriptDefine(Node $node): bool
    {
        return (
            $node instanceof Node\Stmt\Expression
            && $node->expr instanceof Node\Expr\FuncCall
            && $node->expr->name instanceof Node\Name
            && $node->expr->name->toString() === 'define'
            && $node->expr->args[0]->value->value === 'CLI_SCRIPT'
            && (
                // CLI_SCRIPT can actually have any truthy value in Moodle but we assume it's 1 or true and not something stupid.
                ($node->expr->args[1]->value instanceof Node\Scalar\LNumber
                    && $node->expr->args[1]->value->value === 1)
                || ($node->expr->args[1]->value instanceof Node\Expr\ConstFetch
                    && $node->expr->args[1]->value->name->toString() === 'true')
            )
        );
    }
}