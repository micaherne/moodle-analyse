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

        if (Util::isCliScriptDefine($node)) {
            $this->isCliScript = true;
        }
    }




}