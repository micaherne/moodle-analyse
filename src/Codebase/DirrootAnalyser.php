<?php

declare(strict_types=1);

namespace MoodleAnalyse\Codebase;

use MoodleAnalyse\Visitor\PathResolvingVisitor;
use PhpParser\Node;

/**
 * This class attempts to work out what is happening where a path node (provided by PathResolvingVisitor) resolves
 * to dirroot, with or without a trailing directory separator. This often happens where some path wrangling is
 * happening, e.g.:
 *
 * * check whether a path is relative or absolute (if (strpos($record['packagefilepath'], $CFG->dirroot) !== 0))
 * * convert an absolute path to a relative one ($file = substr(__FILE__, strlen($CFG->dirroot.'/'))
 *
 * Maybe worth noting that there is no relative to absolute path conversion as this is taken account of by the
 * path resolver (e.g. something like $CFG->dirroot . $variable will be turned into @{$variable} and replaced
 * with a core_codebase::path() call.
 */
class DirrootAnalyser
{

    public const UNCLASSIFIED = 0;
    public const NEGATIVE = 1; // Is it a negative assertion?
    public const ABSOLUTE_PATH_CHECK = 2;
    public const ABSOLUTE_PATH_TO_RELATIVE = 4;


    /**
     * Is the resolved include just dirroot with an optional trailing directory separator?
     *
     * @param string $resolvedInclude
     * @return bool
     */
    public function isDirroot(string $resolvedInclude): bool
    {
        return $resolvedInclude === '@' || $resolvedInclude === '@/' || $resolvedInclude === '@\\'
            || $resolvedInclude === '@{DIRECTORY_SEPARATOR}' || $resolvedInclude === '@{\\DIRECTORY_SEPARATOR}';
    }

    /**
     * Classify what dirroot is being used for.
     *
     * @param Node $pathNode
     * @return array{Node, int}
     */
    public function classifyUse(Node $pathNode): array
    {
        $parent = $pathNode->getAttribute('parent');

        if ($parent instanceof Node\Arg) {
            $argParent = $parent->getAttribute('parent');
            if ($argParent instanceof Node\Expr\FuncCall) {
                $functionName = $argParent->name->toString();

                if ($functionName === 'strpos') {
                    if ($argParent->args[1] === $parent) {
                        $functionParent = $argParent->getAttribute('parent');
                        if ($functionParent instanceof Node\Expr\BinaryOp) {
                            if ($functionParent->left === $argParent) {
                                $compareNode = $functionParent->right;
                            } elseif ($functionParent->right == $argParent) {
                                $compareNode = $functionParent->left;
                            } else {
                                echo "This should never happen\n";
                                return [$pathNode, self::UNCLASSIFIED];
                            }
                        } else {
                            echo "Unexpected function parent\n";
                        }

                        if ($compareNode instanceof Node\Scalar\LNumber) {
                            if ($compareNode->value === 0) {
                                $functionGrandparent = $functionParent->getAttribute('parent');
                                if ($functionGrandparent instanceof Node\Stmt\If_) {
                                    // This is complicated, as we don't can't tell whether this is a
                                    // "is this file in the codebase" check or a bit of code that deals
                                    // with both relative and absolute paths. (There are both.)
                                    //
                                    // Update: the only bit of the core codebase that seems to deal with
                                    // relative and absolute paths is cache/classes/definition.php where
                                    // a plugin is allowed to declare an overrideclassfile or datasourceclassfile
                                    // as an absolute path (as long as it starts with $CFG->dirroot). This is
                                    // just fucking stupid and I'm inclined to raise a tracker issue to try to get
                                    // it removed.
                                    $classification = self::ABSOLUTE_PATH_CHECK;
                                    if ($functionParent instanceof Node\Expr\BinaryOp\NotIdentical
                                        || $functionParent instanceof Node\Expr\BinaryOp\NotEqual) {
                                        $classification |= self::NEGATIVE;
                                    }
                                    return [$functionParent, $classification];
                                } else {
                                    echo "This is maybe surprising!\n";
                                }
                            } else {
                                echo "Unexpected strpos comparison with non-zero\n";
                                return [$pathNode, self::UNCLASSIFIED];
                            }
                        }
                    } else {
                        echo "Unexpected use of dirroot as first argument in strpos\n";
                        return [$pathNode, self::UNCLASSIFIED];
                    }
                } elseif ($functionName === 'strlen') {
                    $functionParent = $argParent->getAttribute('parent');
                    if ($functionParent instanceof Node\Arg) {
                        $parentFunction = $functionParent->getAttribute('parent');
                        $parentFunctionName = $parentFunction->name->toString();
                        if ($parentFunctionName === 'substr') {
                            // TODO: We need to check this further.
                            return [$parentFunction, self::ABSOLUTE_PATH_TO_RELATIVE];
                        } else {
                            echo "What is this?\n";
                        }
                    } elseif ($functionParent instanceof Node\Expr\BinaryOp\Plus) {
                        echo "Probably making relative but chopping off the leading slash too - need to check for substr\n";
                    } else {
                        // Could be variable assignment.
                        echo "Unexpected strlen parent\n";
                    }
                } elseif ($functionName === 'str_replace') {
                    if ($argParent->args[0] === $parent && $argParent->args[1]->value instanceof Node\Scalar\String_
                        && $argParent->args[1]->value->value === '') {
                        return [$argParent, self::ABSOLUTE_PATH_TO_RELATIVE];
                    }
                } else {
                    // cli_execute_parallel, realpath, strrev (in is_dataroot_insecure), preg_quote, recurseFolders
                    // define (in pdflib), is_writable, symlink, testing_cli_fix_directory_separator, chdir
                    echo "Unknown function: $functionName\n";
                }
            } elseif ($argParent instanceof Node\Expr\MethodCall) {
                $methodName = '$' . $argParent->var->name . '->' . $argParent->name->toString() . '()';
                echo "Unknown method: $methodName\n";
            } else {
                $argParentClass = get_class($argParent);
                echo "Unknown argument parent: $argParentClass\n";
            }
        } else {
            if (is_object($parent)) {
                $parentClass = get_class($parent);
                echo "Unknown parent: $parentClass\n";
            } else {
                echo "Parent not found\n";
            }

        }

        // Try and return the containing expression.
        if ($pathNode->hasAttribute(PathResolvingVisitor::CONTAINING_EXPRESSION)) {
            return [$pathNode->getAttribute(PathResolvingVisitor::CONTAINING_EXPRESSION), self::UNCLASSIFIED];
        }

        return [$parent, self::UNCLASSIFIED];
    }



}