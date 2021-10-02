<?php

namespace MoodleAnalyse\Visitor;

use JetBrains\PhpStorm\Pure;
use PhpParser\Node;
use PhpParser\Node\Expr\Include_;
use PhpParser\NodeVisitor\FindingVisitor;
use Symfony\Component\Finder\SplFileInfo;

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

    const AFTER_CONFIG_INCLUDE = 'afterConfigInclude';
    private string $filePath;

    private const INCLUDE_CONTRIBUTION = 'includeContribution';
    public const RESOLVED_INCLUDE = 'resolvedInclude';

    private bool $insideInclude = false;

    private bool $afterConfigInclude = false;

    public function __construct()
    {
        parent::__construct(fn(Node $node) => $node instanceof Include_);
    }

    public function beforeTraverse(array $nodes)
    {
        $this->afterConfigInclude = false;
    }

    public function setFile(SplFileInfo $file): void
    {
        $this->filePath = str_replace('\\', '/', $file->getRelativePathname());
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
         * Add the path component if necessary. This is mainly string-like components, more structured
         * ones such as variables and method calls are dealt with in leaveNode().
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
        } elseif ($node instanceof Node\Scalar\MagicConst\Dir) {
            $this->setPathComponent($node, '@/' . dirname($this->filePath));
        } elseif ($node instanceof Node\Scalar\MagicConst\File) {
            $this->setPathComponent($node, '@/' . $this->filePath);
        }
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
                    $this->overridePathComponent($node, dirname($argumentNode->getAttribute(self::INCLUDE_CONTRIBUTION)));
                }
            } else {
                // Only supports function calls with no parameters.
                $args = [];
                foreach ($node->args as $arg) {
                    if ($arg->value instanceof Node\Scalar\String_) {
                        $args[] = "'" . $arg->value->value . "'";
                    } else {
                        $args[] = $this->getPathComponentNoBraces($arg);
                    }
                }
                $this->overridePathComponent($node, '{' . $node->name->toCodeString() . '(' . implode(', ', $args) . ')}');
            }
        } elseif ($node instanceof Node\Expr\Variable) {
            if ($node->name === 'CFG') {
                return null;
            }
            $this->overridePathComponent($node, '{$' . $node->name . '}');
        } elseif ($node instanceof Node\Expr\MethodCall) {
            // This only supports method calls with no parameters.
            $this->overridePathComponent($node, '{' . $this->getPathComponentNoBraces($node->var) . '->' . $node->name->name . '()}');
        } elseif ($node instanceof Node\Expr\ArrayDimFetch) {
            if ($node->dim instanceof Node\Scalar\String_) {
                $dim = "'" . $node->dim->value . "'";
            } elseif ($node->dim instanceof Node\Scalar\LNumber) {
                $dim = $node->dim->value;
            } else {
                $dim = '$' . $node->dim->name;
            }
            $this->overridePathComponent($node, '{' . $this->getPathComponentNoBraces($node->var) . '[' . $dim . ']}');
        } elseif ($node instanceof Node\Expr\PropertyFetch) {
            if (!($node->var instanceof Node\Expr\Variable && $node->var->name === 'CFG')) {
                $this->overridePathComponent($node, '{' . $this->getPathComponentNoBraces($node->var) . '->' . $node->name->name . '}');
            }
        }

        $node->setAttribute(self::AFTER_CONFIG_INCLUDE, $this->afterConfigInclude);

        $this->updateParentPath($node);

        if ($node instanceof Include_) {
            $this->insideInclude = false;
            $rawPath = $node->getAttribute(self::INCLUDE_CONTRIBUTION);
            $node->setAttribute(self::RESOLVED_INCLUDE, $this->normalise($rawPath));
            if ($this->normalise($rawPath) === '@/config.php') {
                $this->afterConfigInclude = true;
            }
        }
    }


    private function updateParentPath(Node $node): void
    {

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

    private function getPathComponent(Node $node): ?string
    {
        return $node->getAttribute(self::INCLUDE_CONTRIBUTION);
    }

    /**
     * @param Node $node
     * @return string
     */
    private function getPathComponentNoBraces(Node $node): ?string
    {
        $component = $this->getPathComponent($node);
        if (is_null($component)) {
            return null;
        }
        return trim($component, '{}');
    }

    private function setPathComponent(Node $node, string $value): void
    {
        $existing = $node->getAttribute(self::INCLUDE_CONTRIBUTION);
        if (!is_null($existing)) {
            throw new \Exception("Node already has component value");
        }
        $node->setAttribute(self::INCLUDE_CONTRIBUTION, $value);
    }

    private function overridePathComponent(Node $node, string $value): void
    {
        $node->setAttribute(self::INCLUDE_CONTRIBUTION, $value);
    }

    private function normalise(string $path): string
    {
        $path = $this->fixPearLibraries($path);
        if (!str_starts_with($path, '@') && !str_starts_with($path, '{')) {
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

    private function handleDots(string $path): string
    {
        if (str_contains($path, '/../')) {
            $pattern = '/\/[^.\/]+\/\.\.\//';
            while (preg_match($pattern, $path)) {
                $path = preg_replace($pattern, '/', $path);
            }
        }
        if (str_contains($path, '/./')) {
            $path = str_replace('/./', '/', $path);
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