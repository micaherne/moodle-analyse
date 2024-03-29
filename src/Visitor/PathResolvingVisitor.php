<?php

declare(strict_types=1);

namespace MoodleAnalyse\Visitor;

use Exception;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use SplStack;
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
class PathResolvingVisitor extends NodeVisitorAbstract implements FileAwareInterface
{

    public const AFTER_CONFIG_INCLUDE = 'afterConfigInclude';

    // The expression node (variable assignment, require, function call etc.) containing the path node.
    public const CONTAINING_EXPRESSION = 'containingExpression';
    public const RESOLVED_INCLUDE = 'resolvedInclude';

    // Private as it's for internal use by this class only.
    private const INCLUDE_CONTRIBUTION = 'includeContribution';

    // Constants for different kind of get_plugin_list() related nodes.

    /** @var string e.g. $x = core_component::get_plugin_list('mod') */
    const COMPONENT_ASSIGNMENT = 'assignment';
    /** @var string e.g. foreach(core_component::get_plugin_list('mod') as $x) */
    const COMPONENT_ITERATOR = 'iterator';
    /** @var string e.g. $x = core_component::get_plugin_list('mod'); $y = $x['assign']; */
    const COMPONENT_INSTANCE = 'instance';

    const GET_PLUGIN_LIST_VARS = 'getPluginListVars';
    const ASSIGNED_PATH_VARS = 'assignedPathVars';

    /** @var string this uses a variable that has come from a core_component call */
    public const FROM_CORE_COMPONENT = 'fromCoreComponent';

    /** @var string this uses a variable that was assigned from a previously identified path */
    public const ASSIGNED_FROM_PATH_VAR = 'assignedPathVariable';

    public const IS_CONFIG_INCLUDE = 'isConfigInclude';
    const GLOBAL_VARIABLES = 'globalVariables';

    /** @var string has core_component::get_plugin_list() been called in this scope? */
    const CORE_COMPONENT_CALLED = 'coreComponentCalled';

    /**
     * @var string do we *know* that $CFG is available at this point?
     * This is useful for checking whether we can substitute a $CFG->dirroot path.
     *
     * Note that this is subtly different from "has config.php been called" as it takes account
     * of function scope, so if the function doesn't have "global $CFG" it will return false
     * regardless of whether $CFG actually exists.
     *
     * So don't use it to determine if any classes are available!
     */
    public const CFG_AVAILABLE = 'cfgAvailable';

    /** @var string has the $CFG variable been accessed at this point in the scope? */
    private const CFG_USED = 'cfgUsed';


    private string $filePath;

    private bool $insidePathNode = false;

    private bool $afterConfigInclude = false;

    private bool $hasMoodleInternalCheck = false;

    /** @var Node[] */
    private array $pathNodes;

    private SplStack $scopeStack;

    public function beforeTraverse(array $nodes)
    {
        $this->afterConfigInclude = false;
        $this->hasMoodleInternalCheck = false;
        $this->pathNodes = [];
        $this->scopeStack = new SplStack();
        $this->addScope();
    }

    private function addScope()
    {
        // We have to get a reference to this when changing it, so an object is easier.
        $this->scopeStack->push(
            (object)[
                self::GET_PLUGIN_LIST_VARS => [],
                self::ASSIGNED_PATH_VARS => [],
                self::GLOBAL_VARIABLES => [],
                self::CFG_USED => false,
                self::CORE_COMPONENT_CALLED => false,
            ]
        );
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

        if ($node instanceof Node\FunctionLike) {
            $this->addScope();
        }

        if (Util::isMoodleInternalCheck($node)) {
            $this->hasMoodleInternalCheck = true;
        }

        $currentScope = $this->scopeStack->top();

        if ($node instanceof Node\Stmt\Global_) {
            foreach ($node->vars as $var) {
                if ($var instanceof Node\Expr\Variable) {
                    $currentScope->
                    {
                    self::GLOBAL_VARIABLES
                    }

                    [$var->name] = 1;
                }
            }
        }

        if ($node instanceof Node\Expr\PropertyFetch && ($node->var instanceof Node\Expr\Variable && $node->var->name === 'CFG')) {
            $currentScope->{self::CFG_USED} = true;
        }

        $this->checkForCoreComponentCalls($node);

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
            $this->setPathComponent($node, (string)$node->value);
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
            // Disable DIRECTORY_SEPARATOR check at the moment.
            // This is fine for includes, but can cause problems with strpos etc.
            // if ($node->name->parts[0] === 'DIRECTORY_SEPARATOR') {
            //     $this->setPathComponent($node, '/');
            // } else {
            $this->setPathComponent($node, '{' . $node->name->toCodeString() . '}');
            // }
        } elseif ($node instanceof Node\Expr\ClassConstFetch) {
            if ($node->class instanceof Node\Name && $node->name instanceof Node\Identifier) {
                $this->setPathComponent(
                    $node,
                    '{' . $node->class->toCodeString()
                    . '::' . $node->name->toString() . '}'
                );
            }
        } elseif ($node instanceof Node\Expr\StaticPropertyFetch) {
            if ($node->class instanceof Node\Name && $node->name instanceof Node\Identifier) {
                $this->setPathComponent(
                    $node,
                    '{' . $node->class->toCodeString()
                    . '::$' . $node->name->toString() . '}'
                );
            }
        }
        // TODO: Deal with PATH_SEPARATOR.
    }

    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\FunctionLike) {
            $this->scopeStack->pop();
        }

        if (!$this->insidePathNode) {
            return null;
        }

        $this->overridePathComponents($node);

        $node->setAttribute(self::AFTER_CONFIG_INCLUDE, $this->afterConfigInclude);

        $this->updateParentPath($node);

        if ($this->isPathNode($node)) {
            $this->insidePathNode = false;
            $rawPath = $node->getAttribute(self::INCLUDE_CONTRIBUTION);
            $resolvedInclude = $this->normalise($rawPath);
            $node->setAttribute(self::RESOLVED_INCLUDE, $resolvedInclude);
            $expressionNode = $this->findParentExpressionNode($node);

            $currentScope = $this->scopeStack->top();

            // Check whether we know that $CFG is available at this point.
            // If not set this doesn't mean that it isn't, just that we can't assert it.
            // (This should also theoretically check for use($CFG) on functions, but that would probably be more
            // hassle than it's worth as it would mean recursing through the scope stack.)
            if (isset($currentScope->{self::GLOBAL_VARIABLES}['CFG'])
                || $currentScope->{self::CFG_USED}
                || ($this->inMainScope() && $this->hasMoodleInternalCheck)
                || ($this->inMainScope() && $node->getAttribute(self::AFTER_CONFIG_INCLUDE))) {
                $node->setAttribute(self::CFG_AVAILABLE, true);
            }

            if (!is_null($expressionNode)) {
                $node->setAttribute(self::CONTAINING_EXPRESSION, $expressionNode);
                if ($expressionNode instanceof Node\Expr\Assign && $expressionNode->var instanceof Node\Expr\Variable) {
                    $currentScope->{self::ASSIGNED_PATH_VARS}[$expressionNode->var->name] = self::ASSIGNED_FROM_PATH_VAR;
                }
            }

            $matches = [];
            if (preg_match('#^{\$(.+?)}.*#', $resolvedInclude, $matches)) {
                $currentScope = $this->scopeStack->top();
                $pluginListVars = $currentScope->{self::GET_PLUGIN_LIST_VARS};
                if (array_key_exists($matches[1], $pluginListVars)
                    && in_array($pluginListVars[$matches[1]], [self::COMPONENT_ITERATOR, self::COMPONENT_INSTANCE])) {
                    $node->setAttribute(self::FROM_CORE_COMPONENT, true);
                } elseif (str_contains($matches[1], '[')) {
                    $withoutDim = substr($matches[1], 0, strpos($matches[1], '['));
                    if (array_key_exists(
                            $withoutDim,
                            $pluginListVars
                        ) && $pluginListVars[$withoutDim] === self::COMPONENT_INSTANCE) {
                        $node->setAttribute(self::FROM_CORE_COMPONENT, true);
                    }
                }
                $assignedPathVars = $currentScope->{self::ASSIGNED_PATH_VARS};
                if (array_key_exists($matches[1], $assignedPathVars)) {
                    $node->setAttribute(self::ASSIGNED_FROM_PATH_VAR, true);
                }
            }

            if ($resolvedInclude === '@/config.php' && $expressionNode instanceof Node\Expr\Include_) {
                $expressionNode->setAttribute(self::IS_CONFIG_INCLUDE, true);
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

        if ($parent instanceof Node\Expr\Ternary) {
            return $node;
        }

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

    private function getPathComponentNoBraces(Node $node): ?string
    {
        $component = $this->getPathComponent($node);
        if (is_null($component)) {
            return null;
        }
        return trim($component, '{}');
    }

    /**
     * @throws Exception
     */
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
        return $this->handleDots($path);
    }

    /**
     * The lib/pear directory is added to the include_path in setup.php, so some things that appear to be
     * relative paths are actually not.
     *
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

    private function isPathNode(Node $node): bool
    {
        return $node->hasAttribute(PathFindingVisitor::IS_CODEPATH_NODE)
            && $node->getAttribute(PathFindingVisitor::IS_CODEPATH_NODE);
    }

    /**
     * @param Node\Expr\FuncCall|Node $node
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

    /**
     * Look for calls to core_component::get_plugin_list
     */
    private function checkForCoreComponentCalls(Node $node): void
    {
        if ($node instanceof Node\Expr\StaticCall && $node->class instanceof Node\Name && ltrim(
                $node->class->toString(),
                '\\'
            ) === 'core_component') {
            $currentScope = $this->scopeStack->top();
            $currentScope->{self::CORE_COMPONENT_CALLED} = true;

            // Look for get_plugin_list(), get_plugin_list_with_class(), get_plugin_list_with_file()
            if ($node->name instanceof Node\Identifier && str_starts_with($node->name->name, 'get_plugin_list')) {
                $parent = $node->getAttribute('parent');
                if ($parent instanceof Node\Stmt\Foreach_) {
                    $currentScope->{self::GET_PLUGIN_LIST_VARS}[$parent->valueVar->name] = self::COMPONENT_ITERATOR;
                } elseif ($parent instanceof Node\Expr\Assign) {
                    $currentScope->{self::GET_PLUGIN_LIST_VARS}[$parent->var->name] = self::COMPONENT_ASSIGNMENT;
                } elseif ($parent instanceof Node\Arg) {
                    // Seems to be mostly array_keys and array_key_exists, which we don't need to care about.
                    echo ("what happens here? " . $this->filePath . ':' . $parent->getStartLine() . "\n");
                } else {
                    // Can be return, e.g. get_plugin_list() in deprecatedlib.php
                    echo ("what happens here? " . $this->filePath . ':' . $parent->getStartLine() . "\n");
                }
            } elseif ($node->name instanceof Node\Identifier && ($node->name->name === 'get_plugin_directory' ||
                    $node->name->name === 'get_component_directory')) {
                $parent = $node->getAttribute('parent');
                if ($parent instanceof Node\Expr\Assign) {
                    $currentScope->{self::GET_PLUGIN_LIST_VARS}[$parent->var->name] = self::COMPONENT_INSTANCE;
                } elseif ($parent instanceof Node\Expr\BinaryOp\Concat && $parent->left === $node) {
                    $concatNode = $parent;
                    // We only care about the left side as that is the only bit that can realistically
                    // be a directory from core_component.
                    while (($concatParent = $concatNode->getAttribute('parent')) instanceof Node\Expr\BinaryOp\Concat
                        && $concatParent->left === $concatNode) {
                        $concatNode = $concatParent;
                    }

                    if ($concatParent instanceof Node\Expr\Assign && $concatParent->var instanceof Node\Expr\Variable) {
                        $currentScope->{self::GET_PLUGIN_LIST_VARS}[$concatParent->var->name] = self::COMPONENT_INSTANCE;
                    }
                }
            }
        }

        $currentScope = $this->scopeStack->top();
        if ($currentScope->{self::CORE_COMPONENT_CALLED}) {
            if ($node instanceof Node\Stmt\Foreach_) {
                if ($node->expr instanceof Node\Expr\Variable && array_key_exists($node->expr->name, $currentScope->{self::GET_PLUGIN_LIST_VARS})) {
                    $currentScope->{self::GET_PLUGIN_LIST_VARS}[$node->valueVar->name] = self::COMPONENT_ITERATOR;
                }
            } elseif ($node instanceof Node\Expr\ArrayDimFetch) {
                if ($node->var instanceof Node\Expr\Variable && array_key_exists($node->var->name, $currentScope->{self::GET_PLUGIN_LIST_VARS})) {
                    if ($node->dim instanceof Node\Scalar\String_) {
                        $currentScope->{self::GET_PLUGIN_LIST_VARS}[$node->var->name . '[\'' . $node->dim->value . '\']'] = self::COMPONENT_INSTANCE;
                    } elseif ($node->dim instanceof Node\Expr\Variable) {
                        $currentScope->{self::GET_PLUGIN_LIST_VARS}[$node->var->name . '[$' . $node->dim->name . ']'] = self::COMPONENT_INSTANCE;
                    }
                }
            } elseif ($node instanceof Node\Expr\Assign) {
                if ($node->expr instanceof Node\Expr\ArrayDimFetch && $node->expr->var instanceof Node\Expr\Variable) {
                    if (array_key_exists($node->expr->var->name, $currentScope->{self::GET_PLUGIN_LIST_VARS})) {
                        $currentScope->{self::GET_PLUGIN_LIST_VARS}[$node->var->name] = self::COMPONENT_INSTANCE;
                    }
                } elseif ($node->expr instanceof Node\Expr\BinaryOp\Concat) {
                    $concatNode = $node->expr;
                    // We only care about the left side as that is the only bit that can realistically
                    // be a directory from core_component.
                    while ($concatNode->left instanceof Node\Expr\BinaryOp\Concat) {
                        $concatNode = $concatNode->left;
                    }
                    if ($concatNode->left instanceof Node\Expr\Variable && array_key_exists($concatNode->left->name, $currentScope->{self::GET_PLUGIN_LIST_VARS})) {
                        // TODO: $node->var may be an ArrayDimFetch.
                        if ($node->var instanceof Node\Expr\ArrayDimFetch) {
                            echo "ArrayDimFetch in " . $this->filePath . ':' . $node->getStartLine() . "\n";
                        } else {
                            $currentScope->{self::GET_PLUGIN_LIST_VARS}[$node->var->name] = self::COMPONENT_INSTANCE;
                        }
                    }
                } elseif ($node->expr instanceof Node\Scalar\Encapsed) {
                    if ($node->expr->parts[0] instanceof Node\Expr\Variable && array_key_exists(
                        $node->expr->parts[0]->name,
                        $currentScope->{self::GET_PLUGIN_LIST_VARS}
                    )) {
                        $currentScope->{self::GET_PLUGIN_LIST_VARS}[$node->var->name] = self::COMPONENT_INSTANCE;
                    }
                }
            }
        }
    }

    private function overridePathComponents(mixed $node): void
    {
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
                // TODO: $node->name could be an ArrayDimFetch (although not in current Moodle)
                $this->overridePathComponent(
                    $node,
                    '{' . $node->name->toCodeString() . '(' . implode(', ', $args) . ')}'
                );
            }
        } elseif ($node instanceof Node\Expr\Variable) {
            if ($node->name !== 'CFG') {
                $this->overridePathComponent($node, '{$' . $node->name . '}');
            }
        } elseif ($node instanceof Node\Expr\MethodCall) {
            $args = $this->getArgValues($node);
            if (is_null($node->name)) {
                echo "Node name is null in " . $this->filePath . ':' . $node->getStartLine() . "\n";
            }
            $this->overridePathComponent(
                $node,
                '{' . $this->getPathComponentNoBraces($node->var) . '->'
                . $node->name->name . '(' . implode(', ', $args) . ')}'
            );
        } elseif ($node instanceof Node\Expr\StaticCall) {
            $args = $this->getArgValues($node);
            if ($node->class instanceof Node\Name && $node->name instanceof Node\Identifier) {
                $this->overridePathComponent(
                    $node,
                    '{' . $node->class->toCodeString()
                    . '::' . $node->name->toString() . '(' . implode(', ', $args) . ')}'
                );
            }
        } elseif ($node instanceof Node\Expr\ArrayDimFetch) {
            // TODO: $node->var might be a StaticPropertyFetch in which case $node->dim is null.
            if ($node->dim instanceof Node\Scalar\String_) {
                $dim = "'" . $node->dim->value . "'";
            } elseif ($node->dim instanceof Node\Scalar\LNumber) {
                $dim = $node->dim->value;
            } else {
                if (is_null($node->dim)) {
                    $dim = '';
                } else {
                    $dim = '$' . $node->dim->name;
                }

            }
            $this->overridePathComponent($node, '{' . $this->getPathComponentNoBraces($node->var) . '[' . $dim . ']}');
        } elseif ($node instanceof Node\Expr\PropertyFetch) {
            $isCfgNode = $node->var instanceof Node\Expr\Variable && $node->var->name === 'CFG';
            if (!$isCfgNode) {
                $this->overridePathComponent(
                    $node,
                    '{' . $this->getPathComponentNoBraces($node->var) . '->' . $node->name->name . '}'
                );
            } elseif (!in_array($node->name->name, ['dirroot', 'libdir', 'admin'])) {
                // Deal with things like $CFG->moodlepageclassfile
                $this->overridePathComponent($node, '{$CFG->' . $node->name->name . '}');
            }
        } elseif ($node instanceof Node\Expr\ClassConstFetch) {
            if ($node->class instanceof Node\Name && $node->name instanceof Node\Identifier) {
                $this->overridePathComponent(
                    $node,
                    '{' . $node->class->toCodeString()
                    . '::' . $node->name->toString() . '}'
                );
            }
        }
    }

    private function inMainScope(): bool
    {
        return $this->scopeStack->count() === 1;
    }

}