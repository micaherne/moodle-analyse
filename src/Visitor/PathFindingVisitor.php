<?php
declare(strict_types=1);

namespace MoodleAnalyse\Visitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * For preprocessing. Find any nodes that look like paths to the codebase.
 */
class PathFindingVisitor extends NodeVisitorAbstract {

    public const IS_CODEPATH_NODE = 'isCodepathNode';

    private array $pathNodes;

    public function beforeTraverse(array $nodes)
    {
        $this->pathNodes = [];
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Expr\Include_) {
            $this->markAsCodePath($node->expr);
            // Return here as it is definitely a code path and there are no children.
            return;
        }

        if ($node instanceof Node\Expr\PropertyFetch) {
            if ($node->var instanceof Node\Expr\Variable && $node->var->name === 'CFG') {
                if ($node->name instanceof Node\Identifier && ($node->name->name == 'dirroot' || $node->name->name === 'libdir')) {
                    $this->findPotentialFilePath($node);
                }
            }
        }

        if ($node instanceof Node\Scalar\MagicConst\Dir || $node instanceof Node\Scalar\MagicConst\File) {
            $this->findPotentialFilePath($node);
        }
    }

    private function findRelevantParent(Node $node): ?Node {
        if (!$node->hasAttribute('parent')) {
            return $node;
        }

        $parent = $node->getAttribute('parent');
        if ($parent instanceof Node\Expr\Include_) {
            if ($parent->expr === $node) {
                return $node;
            } else {
                echo "what happens here?\n";
            }
        }

        if ($parent instanceof Node\Arg) {
            $function = $parent->getAttribute('parent');

            // We need to ignore dirname() as this is likely to be part of the path.
            if ($this->isDirnameCall($function)) {
                return $this->findRelevantParent($parent);
            } else {
                if ($parent->value === $node) {
                    return $node;
                } else {
                    echo "what happens here?\n";
                }
            }

        }

        if ($parent instanceof Node\Expr\Assign) {
            if ($parent->expr === $node) {
                return $node;
            } else {
                if ($parent->var === $node) {
                    // It's an assignment to e.g. $CFG->dirroot
                    return null;
                } else {
                    echo "what?";
                }
            }
        }

        // For example ['index' => $CFG->dirroot]
        if ($parent instanceof Node\Expr\ArrayItem) {
            if ($parent->value === $node) {
                return $node;
            } else {
                echo "what happens here?";
            }
        }

        // e.g. return $CFG->dirroot . '/some/path.php'
        if ($parent instanceof Node\Stmt\Return_) {
            if ($parent->expr === $node) {
                return $node;
            } else {
                echo "what happens here?";
            }
        }

        if ($parent instanceof Node\Expr\Ternary) {
            return $node;
        }

        return $this->findRelevantParent($parent);

    }

    /**
     * @return array
     */
    public function getPathNodes(): array
    {
        return $this->pathNodes;
    }

    /**
     * Given a node like __DIR__ or $CFG->dirroot, look for a parent node like a variable assignment or function call
     * and mark the relevant node as a code path node if found.
     *
     * @param Node $node
     */
    private function findPotentialFilePath(Node $node): void
    {
        $relevantParent = $this->findRelevantParent($node);

        // Null means it looked like a path node but wasn't.
        if (!is_null($relevantParent)) {
            $this->markAsCodePath($relevantParent);
            $this->pathNodes[] = $relevantParent;
        }
    }

    /**
     * @param Node $node
     */
    private function markAsCodePath(Node $node): void
    {
        $node->setAttribute(self::IS_CODEPATH_NODE, true);
    }

    /**
     * @param Node $function
     * @return bool
     */
    private function isDirnameCall(Node $function): bool
    {
        return $function instanceof Node\Expr\FuncCall && $function->name instanceof Node\Name && $function->name->parts[0] === 'dirname';
    }


}
