<?php
declare(strict_types=1);

namespace MoodleAnalyse\Visitor;

use Exception;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
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
class PathResolvingVisitor extends NodeVisitorAbstract
{

    public const AFTER_CONFIG_INCLUDE = 'afterConfigInclude';

    // The expression node (variable assignment, require, function call etc.) containing the path node.
    public const CONTAINING_EXPRESSION = 'containingExpression';
    public const RESOLVED_INCLUDE = 'resolvedInclude';

    // Private as it's for internal use by this class only.
    private const INCLUDE_CONTRIBUTION = 'includeContribution';

    private string $filePath;

    private bool $insidePathNode = false;

    private bool $afterConfigInclude = false;

    /** @var Node[] */
    private array $pathNodes;

    public function beforeTraverse(array $nodes)
    {
        $this->afterConfigInclude = false;
        $this->pathNodes = [];
    }

    public function setFile(SplFileInfo $file): void
    {
        $this->filePath = str_replace('\\', '/', $file->getRelativePathname());
    }

    /**
     * @throws Exception
     */
    public function enterNode(Node $node)
    {
        parent::enterNode($node);

        if ($this->isPathNode($node)) {
            $this->insidePathNode = true;
        }

        if (!$this->insidePathNode) {
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

        // Things like MagicConst\Dir are Node\Scalar too but don't have value.
        if ($node instanceof Node\Scalar && property_exists($node, 'value')) {
            $this->setPathComponent($node, (string) $node->value);
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

            $dirname = dirname($this->filePath);
            if ($dirname === '.') {
                // It's a top level file.
                $this->setPathComponent($node, '@');
            } else {
                $this->setPathComponent($node, '@/' . $dirname);
            }
        } elseif ($node instanceof Node\Scalar\MagicConst\File) {
            $this->setPathComponent($node, '@/' . $this->filePath);
        } elseif ($node instanceof Node\Expr\ConstFetch) {
            if ($node->name->parts[0] === 'DIRECTORY_SEPARATOR') {
                $this->setPathComponent($node, '/');
            } else {
                $this->setPathComponent($node, '{' . $node->name->toCodeString() . '}');
            }
        } elseif ($node instanceof Node\Expr\ClassConstFetch) {
            if ($node->class instanceof Node\Name && $node->name instanceof Node\Identifier) {
                $this->setPathComponent($node, '{' . $node->class->toCodeString()
                    . '::' . $node->name->toString() . '}');
            }
        } elseif ($node instanceof Node\Expr\StaticPropertyFetch) {
            if ($node->class instanceof Node\Name && $node->name instanceof Node\Identifier) {
                $this->setPathComponent($node, '{' . $node->class->toCodeString()
                    . '::$' . $node->name->toString() . '}');
            }
        }

        // TODO: Deal with PATH_SEPARATOR.
    }

    public function leaveNode(Node $node)
    {

        if (!$this->insidePathNode) {
            return;
        }

        if ($node instanceof Node\Expr\FuncCall) {
            if ($node->name instanceof Node\Name && $node->name->parts[0] === 'dirname') {
                $argumentNode = $node->args[0];
                $includeContribution = $argumentNode->getAttribute(self::INCLUDE_CONTRIBUTION);

                // If it goes beyond dirroot, add dots in.
                // This only happens when admin/cli/install.php is looking for the default datadir.
                if ($includeContribution === '@') {
                    $this->overridePathComponent($node, '@/..');
                } else {
                    $this->overridePathComponent($node, dirname($includeContribution));
                }
            } else {
                $args = $this->getArgValues($node);
                $this->overridePathComponent($node, '{' . $node->name->toCodeString() . '(' . implode(', ', $args) . ')}');
            }
        } elseif ($node instanceof Node\Expr\Variable) {
            if ($node->name === 'CFG') {
                return null;
            }
            $this->overridePathComponent($node, '{$' . $node->name . '}');
        } elseif ($node instanceof Node\Expr\MethodCall) {
            $args = $this->getArgValues($node);
            $this->overridePathComponent($node, '{' . $this->getPathComponentNoBraces($node->var) . '->'
                . $node->name->name . '(' . implode(', ', $args) . ')}');
        } else if ($node instanceof Node\Expr\StaticCall) {
            $args = $this->getArgValues($node);
            if ($node->class instanceof Node\Name && $node->name instanceof Node\Identifier) {
                $this->overridePathComponent($node, '{' . $node->class->toCodeString()
                    . '::' . $node->name->toString() . '(' . implode(', ', $args) . ')}');
            }
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
            $isCfgNode = $node->var instanceof Node\Expr\Variable && $node->var->name === 'CFG';
            if (!$isCfgNode) {
                $this->overridePathComponent($node, '{' . $this->getPathComponentNoBraces($node->var) . '->' . $node->name->name . '}');
            } else {
                if (!in_array($node->name->name, ['dirroot', 'libdir', 'admin'])) {
                    // Deal with things like $CFG->moodlepageclassfile
                    $this->overridePathComponent($node, '{$CFG->' . $node->name->name . '}');
                }
            }
        } else if ($node instanceof Node\Expr\ClassConstFetch) {
            if ($node->class instanceof Node\Name && $node->name instanceof Node\Identifier) {
                $this->overridePathComponent($node, '{' . $node->class->toCodeString()
                    . '::' . $node->name->toString() . '}');
            }
        }

        $node->setAttribute(self::AFTER_CONFIG_INCLUDE, $this->afterConfigInclude);

        $this->updateParentPath($node);

        if ($this->isPathNode($node)) {
            $this->insidePathNode = false;
            $rawPath = $node->getAttribute(self::INCLUDE_CONTRIBUTION);
            $node->setAttribute(self::RESOLVED_INCLUDE, $this->normalise($rawPath));
            $expressionNode = $this->findParentExpressionNode($node);

            if (!is_null($expressionNode)) {
                $node->setAttribute(self::CONTAINING_EXPRESSION, $expressionNode);
            }

            if ($this->normalise($rawPath) === '@/config.php') {
                $this->afterConfigInclude = true;
            }
            $this->pathNodes[] = $node;
        }
    }

    /**
     * Walk up through the parent nodes of a path node to find the enclosing expression,
     * e.g. a function call, require or variable assignment.
     */
    public function findParentExpressionNode(Node $node): ?Node
    {
        if (!$node->hasAttribute('parent')) {
            return null;
        }

        $parent = $node->getAttribute('parent');

        if ($parent instanceof Node\Expr) {
            return $parent;
        }

        return $this->findParentExpressionNode($parent);
    }

    /**
     * @return Node[]
     */
    public function getPathNodes(): array
    {
        return $this->pathNodes;
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
     * @return string|null
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
            throw new Exception("Node already has component value");
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
     * @param Node $node
     * @return bool
     */
    private function isPathNode(Node $node): bool
    {
        return $node->hasAttribute('isCodepathNode') && $node->getAttribute('isCodepathNode');
    }

    /**
     * @param Node\Expr\FuncCall|Node $node
     * @return array
     */
    private function getArgValues(Node\Expr\FuncCall|Node $node): array
    {
        $args = [];
        foreach ($node->args as $arg) {
            if ($arg->value instanceof Node\Scalar\String_) {
                $args[] = "'" . $arg->value->value . "'";
            } else {
                $args[] = $this->getPathComponentNoBraces($arg);
            }
        }
        return $args;
    }

}