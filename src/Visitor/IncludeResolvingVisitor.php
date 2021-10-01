<?php

namespace MoodleAnalyse\Visitor;

use JetBrains\PhpStorm\Pure;
use PhpParser\Node;
use PhpParser\Node\Expr\Include_;
use PhpParser\NodeVisitor\FindingVisitor;

/**
 * Resolve includes into a standard format, for example:
 *
 * @/admin/antiviruses.php, where @ signifies the root of the Moodle codebase.
 *
 * Variables, including array fetches and object properties, and method calls are surrounded by
 * braces, e.g.
 *
 * @/user/profile/field/{$proffields[$field]->datatype}/field.class.php
 *
 */
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
            }
        } elseif ($node instanceof Node\Expr\BinaryOp\Concat) {
            // Nothing to do here.
            $this->setPathComponent($node, '');
        } elseif ($node instanceof Node\Scalar\MagicConst\Dir) {
            $this->setPathComponent($node, '@/' . dirname($this->filePath));
        } elseif ($node instanceof Node\Scalar\MagicConst\File) {
            $this->setPathComponent($node, '@/' . $this->filePath);
        } elseif ($node instanceof Node\Expr\Variable) {
            if ($node->name === 'CFG') {
                return null;
            }
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

    private function getPathComponent(Node $node): ?string {
        return $node->getAttribute(self::INCLUDE_CONTRIBUTION);
    }

    /**
     * @param Node $node
     * @return string
     */
    private function getPathComponentNoBraces(Node $node): ?string
    {
        $component = $this->getPathComponent($node->var);
        if (is_null($component)) {
            return null;
        }
        return trim($component, '{}');
    }

    private function setPathComponent(Node $node, string $value): void {
        $existing = $node->getAttribute(self::INCLUDE_CONTRIBUTION);
        if (!is_null($existing)) {
            throw new \Exception("Node already has component value");
        }
        $node->setAttribute(self::INCLUDE_CONTRIBUTION, $value);
    }

    private function overridePathComponent(Node $node, string $value): void {
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
                    $node->setAttribute(self::INCLUDE_CONTRIBUTION, dirname($argumentNode->getAttribute(self::INCLUDE_CONTRIBUTION)));
                }
            }
        } elseif ($node instanceof Node\Expr\Variable) {
            if ($node->name === 'CFG') {
                return null;
            }
            $this->overridePathComponent($node, '{$' . $node->name . '}');
        } elseif ($node instanceof Node\Expr\MethodCall) {
            $this->overridePathComponent($node, '{' . $this->getPathComponentNoBraces($node) . '->' . $node->name->name . '()}');
        } elseif ($node instanceof Node\Expr\ArrayDimFetch) {
            $this->overridePathComponent($node, '{' . $this->getPathComponentNoBraces($node) . '[$' . $node->dim->name . ']}');
        } elseif ($node instanceof Node\Expr\PropertyFetch) {
            if (!($node->var instanceof Node\Expr\Variable && $node->var->name === 'CFG')) {
                $this->overridePathComponent($node, '{' . $this->getPathComponentNoBraces($node) . '->' . $node->name->name . '}');
            }
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