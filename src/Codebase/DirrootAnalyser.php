<?php

declare(strict_types=1);

namespace MoodleAnalyse\Codebase;

use MoodleAnalyse\Visitor\PathResolvingVisitor;
use PhpParser\Node;
use PhpParser\PrettyPrinter\Standard;

/**
 * This class attempts to work out what is happening where a path node (provided by PathResolvingVisitor) resolves
 * to dirroot, with or without a trailing directory separator. This often happens where some path wrangling is
 * happening, e.g.:
 *
 * * check whether a path is relative or absolute (if (strpos($record['packagefilepath'], $CFG->dirroot) !== 0))
 * * convert an absolute path to a relative one ($file = substr(__FILE__, strlen($CFG->dirroot.'/'))
 *
 */
class DirrootAnalyser
{

    public const UNCLASSIFIED = 0;
    public const NEGATIVE = 1; // Is it a negative assertion?
    public const ABSOLUTE_PATH_CHECK = 2;
    public const ABSOLUTE_PATH_TO_RELATIVE = 4;

    public const REPLACE_WITH_STRING = 8;


    /**
     * Is the resolved include just dirroot with an optional trailing directory separator?
     *
     */
    public function isDirroot(string $resolvedInclude): bool
    {
        return $resolvedInclude === '@' || $resolvedInclude === '@/' || $resolvedInclude === '@\\'
            || $resolvedInclude === '@{DIRECTORY_SEPARATOR}' || $resolvedInclude === '@{\\DIRECTORY_SEPARATOR}';
    }

    public function extractWrangle(Node $pathNode, string $fileContents): ?PathCodeDirrootWrangle
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
                                return null;
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
                                    $printer = new Standard();
                                    $variable = $printer->prettyPrintExpr($argParent->args[0]->value);
                                    return $this->createDirrootWrangle($functionParent, $fileContents, $classification, $variable);
                                } else {
                                    echo "This is maybe surprising!\n";
                                }
                            } else {
                                echo "Unexpected strpos comparison with non-zero\n";
                                return null;
                            }
                        }
                    } else {
                        echo "Unexpected use of dirroot as first argument in strpos\n";
                        return null;
                    }
                } elseif ($functionName === 'strlen') {
                    $functionParent = $argParent->getAttribute('parent');
                    if ($functionParent instanceof Node\Arg) {
                        $parentFunction = $functionParent->getAttribute('parent');
                        $parentFunctionName = $parentFunction->name->toString();
                        if ($parentFunctionName === 'substr') {
                            // TODO: We need to check this further.
                            $printer = new Standard();
                            $variable = $printer->prettyPrintExpr($parentFunction->args[0]->value);
                            return $this->createDirrootWrangle($parentFunction, $fileContents, self::ABSOLUTE_PATH_TO_RELATIVE, $variable);
                        } else {
                            // TODO: This could be the length being assigned to a variable for later use in wrangling.
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
                        ) {
                        if ($argParent->args[1]->value->value === '') {
                            $printer = new Standard();
                            $variable = $printer->prettyPrintExpr($argParent->args[2]->value);
                            return $this->createDirrootWrangle(
                                $argParent,
                                $fileContents,
                                self::ABSOLUTE_PATH_TO_RELATIVE,
                                $variable,
                            );
                        } else {
                            $printer = new Standard();
                            $variable = $printer->prettyPrintExpr($argParent->args[2]->value);
                            $other = $printer->prettyPrintExpr($argParent->args[1]->value);
                            return $this->createDirrootWrangle(
                                $argParent,
                                $fileContents,
                                self::REPLACE_WITH_STRING,
                                $variable,
                                $other,
                            );
                        }
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
                $argParentClass = $argParent::class;
                echo "Unknown argument parent: $argParentClass\n";
            }
        } elseif (is_object($parent)) {
            $parentClass = $parent::class;
            echo "Unknown parent: $parentClass\n";
        } else {
            echo "Parent not found\n";
        }

        return null;
    }

    private function createDirrootWrangle(
        Node $wrangleNode,
        string $fileContents,
        int $classification,
        ?string $variableName = null,
        ?string $other = null
    ): PathCodeDirrootWrangle {
        $wrangleCode = substr(
            $fileContents,
            $wrangleNode->getStartFilePos(),
            $wrangleNode->getEndFilePos() - $wrangleNode->getStartFilePos() + 1
        );

        $wrangle = new PathCodeDirrootWrangle(
            $wrangleCode,
            $wrangleNode->getStartLine(),
            $wrangleNode->getEndLine(),
            $wrangleNode->getStartFilePos(),
            $wrangleNode->getEndFilePos(),
            $classification,
            $variableName,
            $other,
        );

        return $wrangle;
    }

    /**
     * Classify what dirroot is being used for.
     *
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
                $argParentClass = $argParent::class;
                echo "Unknown argument parent: $argParentClass\n";
            }
        } elseif (is_object($parent)) {
            $parentClass = $parent::class;
            echo "Unknown parent: $parentClass\n";
        } else {
            echo "Parent not found\n";
        }

        // Try and return the containing expression.
        if ($pathNode->hasAttribute(PathResolvingVisitor::CONTAINING_EXPRESSION)) {
            return [$pathNode->getAttribute(PathResolvingVisitor::CONTAINING_EXPRESSION), self::UNCLASSIFIED];
        }

        return [$parent, self::UNCLASSIFIED];
    }


}