<?php

namespace MoodleAnalyse\Visitor;

use JetBrains\PhpStorm\Pure;
use PhpParser\Node;
use PhpParser\Node\Expr\Include_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\FindingVisitor;
use PhpParser\NodeVisitorAbstract;

class IncludeResolvingVisitor extends FindingVisitor
{

    private string $filePath;

    private const INCLUDE_CONTRIBUTION = 'includeContribution';
    public const RESOLVED_INCLUDE = 'resolvedInclude';

    private bool $insideInclude = false;

    public function __construct()
    {
        parent::__construct(fn(Node $node) => $node instanceof Include_);
    }

    /**
     * @throws \Exception
     */
    public function enterNode(Node $node)
    {
        parent::enterNode($node);

        if ($node instanceof Include_) {
            $this->insideInclude = true;
            return; // We don't want to process this node.
        }

        if (!$this->insideInclude) {
            return;
        }

        /*
         * Add the path component if necessary.
         *
         * We can safely ignore some types as they don't contribute anything on their own:
         *
         * * Identifier
         * * Encapsed
         */
        if ($node instanceof Node\Scalar\String_) {
            $this->setPathComponent($node, $node->value);
        } elseif ($node instanceof Node\Scalar\EncapsedStringPart) {
            $this->setPathComponent($node, $node->value);
        } elseif ($node instanceof Node\Expr\PropertyFetch) {
            if ($node->var instanceof Node\Expr\Variable && $node->var->name === 'CFG') {
                if ($node->name instanceof Node\Identifier && $node->name->name === 'dirroot') {
                    $this->setPathComponent($node, '@');
                } elseif ($node->name instanceof Node\Identifier && $node->name->name === 'libdir') {
                    $this->setPathComponent($node, '@/lib');
                } elseif ($node->name instanceof Node\Identifier && $node->name->name === 'admin') {
                    $this->setPathComponent($node, 'admin');
                }
            } /*else if ($node->name instanceof Node\Identifier) {
                $this->setPathComponent($node, '{$' . $node->var->name . '->' . $node->name->name . '}');
                return NodeTraverser::DONT_TRAVERSE_CHILDREN;
            }*/
        } elseif ($node instanceof Node\Expr\BinaryOp\Concat) {
            // Nothing to do here.
            $this->setPathComponent($node, '');
        } elseif ($node instanceof Node\Scalar\MagicConst\Dir) {
            $this->setPathComponent($node, '@/' . dirname($this->filePath));
        } elseif ($node instanceof Node\Scalar\MagicConst\File) {
            $this->setPathComponent($node, '@/' . $this->filePath);
        } elseif ($node instanceof Node\Expr\Variable) {
            if ($node->name === 'CFG') {
                return;
            }
            $this->setPathComponent($node, '{$' . $node->name . '}');
        } elseif ($node instanceof Node\Expr\MethodCall) {
            $this->setPathComponent($node, '{$' . $node->var->name . '->' . $node->name->name . '()}');
            return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        } elseif ($node instanceof Node\Expr\ArrayDimFetch) {
            $this->setPathComponent($node, '{$' . $node->var->name . '[$' . $node->dim->name . ']');
        }

        else {
            $x = "Unknown node type";
        }

    }

    private function updateParentPath(Node $node): void {

        $value = $node->getAttribute(self::INCLUDE_CONTRIBUTION) ?? '';

        /** @var Node $parent */
        $parent = $node->getAttribute('parent');
        if (is_null($parent)) {
            return;
        }
        $existing = $parent->getAttribute(self::INCLUDE_CONTRIBUTION);
        if (is_null($existing)) {
            $parent->setAttribute(self::INCLUDE_CONTRIBUTION, $value);
        } else {
            $parent->setAttribute(self::INCLUDE_CONTRIBUTION, $existing . $value);
        }
    }

    private function setPathComponent(Node $node, string $value): void {
        $existing = $node->getAttribute(self::INCLUDE_CONTRIBUTION);
        if (!is_null($existing)) {
            throw new \Exception("Node already has component value");
        }
        $node->setAttribute(self::INCLUDE_CONTRIBUTION, $value);
    }

    public function leaveNode(Node $node)
    {

        if (!$this->insideInclude) {
            return;
        }

        if ($node instanceof Node\Expr\FuncCall) {
            if ($node->name instanceof Node\Name && $node->name->parts[0] === 'dirname') {
                $argumentNode = $node->args[0];
                if ($argumentNode->value instanceof Node\Scalar\MagicConst\File) {
                    // TODO: This doesn't work properly.
                    $node->setAttribute(self::INCLUDE_CONTRIBUTION, dirname($argumentNode->getAttribute(self::INCLUDE_CONTRIBUTION)));
                }
            }
        } elseif ($node instanceof Node\Expr\MethodCall) {
            $x = "arse";
        }

        $this->updateParentPath($node);

        if ($node instanceof Include_) {
            $this->insideInclude = false;
            $rawPath = $node->getAttribute(self::INCLUDE_CONTRIBUTION);
            $node->setAttribute(self::RESOLVED_INCLUDE, $this->normalise($rawPath));
            return;
        }
    }

    private function normalise(string $path): string {
        $path = $this->fixPearLibraries($path);
        if (!str_starts_with($path, '@')) {
            $path = '@/' . dirname($this->filePath) . '/' . $path;
        }
        $path = $this->handleDots($path);
        return $path;
    }

    /**
     * The lib/pear directory is added to the include_path in setup.php, so some things that appear to be
     * relative paths are actually not.
     *
     * @param string $path
     * @return string
     */
    private function fixPearLibraries(string $path): string
    {
        if (str_starts_with($path, 'HTML/') || str_starts_with($path, 'PEAR/')) {
            return '@/lib/pear/' . $path;
        }
        return $path;
    }

    private function handleDots(string $path): string {
        if (str_contains($path, '/../')) {
            $pattern = '/\/[^.\/]+\/\.\.\//';
            while(preg_match($pattern, $path)) {
                $path = preg_replace($pattern, '/', $path);
            }
        }
        return $path;
    }


    public function setFilePath(string $filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * @return Include_[]
     */
    #[Pure]
    public function getIncludes(): array
    {
        return $this->getFoundNodes();
    }

}