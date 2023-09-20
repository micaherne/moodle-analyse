<?php

declare(strict_types=1);

namespace MoodleAnalyse\Codebase;

use Exception;
use MoodleAnalyse\Visitor\PathResolvingVisitor;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\FuncCall;
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
    public const ABSOLUTE_PATH_IN_CODEBASE = 2;
    public const ABSOLUTE_PATH_TO_RELATIVE = 4;

    public const REPLACE_WITH_STRING = 8;

    /** @var int for use with absolute path to relative - means the return should have no leading slash */
    public const NO_SLASH = 16;

    /** @var int for replacing str_replace() calls where the path may already be relative */
    public const ALLOW_RELATIVE_PATHS = 32;

    public const DIRROOT_WRANGLE_CATEGORY = 'dirrootWrangleCategory';
    public const DIRROOT_WRANGLE_VARIABLE_NODE = 'dirrootWrangleVariableNode';

    /** @var string if category is replace with string, this annotates the node with the string */
    public const DIRROOT_WRANGLE_REPLACEMENT_NODE = 'dirrootWrangleReplacement';

    private static $validResolvedIncludes = [
        '@',
        '@/',
        '@\\',
        '@{DIRECTORY_SEPARATOR}',
        '@{\\DIRECTORY_SEPARATOR}',
    ];


    /**
     * Is the resolved include just dirroot with an optional trailing directory separator?
     *
     */
    public function isDirroot(string $resolvedInclude): bool
    {
        return in_array($resolvedInclude, self::$validResolvedIncludes, true);
    }

    /**
     * @throws Exception
     */
    public function extractWrangle(Node $pathNode, string $fileContents): ?PathCodeDirrootWrangle
    {
        $wrangleNode = $this->findWrangleNode($pathNode);

        if (is_null($wrangleNode)) {
            return null;
        }

        return $this->pathCodeForWrangle($wrangleNode, $fileContents);
    }

    /**
     * Given a path node, try to identify the outermost node that constitutes a dirroot wrangle.
     *
     * If found, the node will be annotated with the following attributes to describe its function:
     *
     * * dirrootWrangleCategory - one of the constants defined in this class
     * * dirrootWrangleVariableNode - the node that contains the variable being wrangled
     * * dirrootWrangleReplacement (optional - for str_replace() wrangles) - the node that contains the replacement string
     *
     * @param Node $pathNode
     * @return Node|null
     * @throws Exception if not a valid dirroot path node
     */
    private function findWrangleNode(Node $pathNode): ?Node
    {
        // Check it first.
        $resolvedInclude = $pathNode->getAttribute(PathResolvingVisitor::RESOLVED_INCLUDE);
        if (is_null($resolvedInclude) || !$this->isDirroot($resolvedInclude)) {
            throw new Exception("findWrangleNode called with non-dirroot path node");
        }

        // If it's not the argument to a function, then it's not a wrangle.
        if (!$this->isFunctionArg($pathNode)) {
            return null;
        }

        $functionCall = $this->getParentFunctionCall($pathNode);

        if (is_null($functionCall)) {
            return null;
        }

        $functionName = $functionCall->name->toString();

        if ($functionName === 'strlen') {
            return $this->checkStrlen($functionCall, $pathNode);
        } elseif ($functionName === 'strpos') {
            return $this->checkStrpos($functionCall, $pathNode);
        } elseif ($functionName === 'str_replace') {
            return $this->checkStrReplace($functionCall, $pathNode);
        }

        // TODO: Should we be trying to handle any odd relative to absolute path conversions here?
        //  There are a couple where it's things like $CFG->dirroot . str_replace($CFG->dirroot ,'', $var)

        // TODO: Try to handle preg_quote too - there are 3 in core and they're all effectively doing
        //  substr($blah, strlen($CFG->dirroot)). That said the one in lib/outputrequirementslib.php
        //  is then prepending $CFG->dirroot again so we will have to deal with that manually.
        //  Also worth noting is that this has similar issues to str_replace() in that it will accept
        //  either absolute or relative paths.

        return null;
    }

    private function pathCodeForWrangle(Node $wrangleNode, string $fileContents): ?PathCodeDirrootWrangle
    {
        if (!$wrangleNode->hasAttribute(self::DIRROOT_WRANGLE_CATEGORY)) {
            throw new Exception("pathCodeForWrangle called with non-wrangle node");
        }

        $category = $wrangleNode->getAttribute(self::DIRROOT_WRANGLE_CATEGORY);

        $printer = new Standard();

        $variableCode = null;
        $variableNode = $wrangleNode->getAttribute(self::DIRROOT_WRANGLE_VARIABLE_NODE);
        if (!is_null($variableNode)) {
            $variableCode = $printer->prettyPrintExpr($variableNode);
        }

        $replacementCode = null;
        if ($category & self::REPLACE_WITH_STRING) {
            $replacementNode = $wrangleNode->getAttribute(self::DIRROOT_WRANGLE_REPLACEMENT_NODE);
            if (!is_null($replacementNode)) {
                $replacementCode = $printer->prettyPrintExpr($replacementNode);
            }
        }

        $allowRelative = false;
        if ($category & self::ALLOW_RELATIVE_PATHS) {
            $allowRelative = true;
        }

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
            $category,
            variableName: $variableCode,
            replacementString: $replacementCode,
            allowRelativePaths: $allowRelative,
        );

        return $wrangle;
    }

    private function isFunctionArg(Node $pathNode): bool
    {
        $parent = $pathNode->getAttribute('parent');
        return ($parent instanceof Node\Arg);
    }

    private function getParentFunctionCall(Node $pathNode): ?Node\Expr\FuncCall
    {
        $parent = $pathNode->getAttribute('parent');
        if (!($parent instanceof Node\Arg)) {
            throw new Exception("Path node is not a function argument");
        }

        $argParent = $parent->getAttribute('parent');

        if ($argParent instanceof Node\Expr\FuncCall) {
            return $argParent;
        }

        return null;
    }

    /**
     * @throws Exception
     */
    private function isStrlenPlusOne(Node\Expr\FuncCall $strlenFunction): bool
    {
        $parent = $strlenFunction->getAttribute('parent');
        if (!($parent instanceof Node\Expr\BinaryOp\Plus)) {
            return false;
        }

        // Support 1 + strlen($CFG->dirroot) as well as strlen($CFG->dirroot) + 1
        $other = $this->getOtherExprInBinaryOp($parent, $strlenFunction);

        if ($other instanceof Node\Scalar\LNumber && $other->value === 1) {
            return true;
        }

        return false;
    }

    /**
     * @param Node\Expr\FuncCall $functionCall
     * @return Node\Expr\FuncCall|null
     * @throws Exception
     */
    private function checkStrlen(Node\Expr\FuncCall $functionCall, Node $pathNode): ?Node\Expr\FuncCall
    {
        $resolvedInclude = $pathNode->getAttribute(PathResolvingVisitor::RESOLVED_INCLUDE);

        if ($functionCall->name->toString() !== 'strlen') {
            throw new Exception("checkStrlen called with non-strlen function");
        }

        $isStrlenPlusOne = $this->isStrlenPlusOne($functionCall);

        /** @var Node $argValueNode the potential argument to the outer function */
        $argValueNode = $functionCall;

        // We can't do anything with strlen() if it's not the argument to a substr() call.
        if ($isStrlenPlusOne) {
            if (!$this->isFunctionArg($functionCall->getAttribute('parent'))) {
                return null;
            }

            // If it is a strlen() + 1 then the potential argument to the outer function is the binary op node.
            $argValueNode = $functionCall->getAttribute('parent');
        } elseif (!$this->isFunctionArg($functionCall)) {
            return null;
        }

        // No need to check which argument it is - strlen() takes only one.

        $secondParentFunction = $this->getParentFunctionCall($argValueNode);
        if (is_null($secondParentFunction)) {
            return null;
        }
        $secondParentFunctionName = $secondParentFunction->name->toString();

        if ($secondParentFunctionName !== 'substr') {
            return null;
        }

        // Now we need to check that our strlen is either the direct second argument, or a strlen + 1 construct is.

        $arg = $functionCall->getAttribute('parent');

        $category = self::ABSOLUTE_PATH_TO_RELATIVE;

        if ($isStrlenPlusOne) {
            $arg = $functionCall->getAttribute('parent')->getAttribute('parent');
            $category |= self::NO_SLASH;
        } elseif (strlen($resolvedInclude) > 1) {
            // If the resolved include is more than just dirroot, then it's a relative path.
            $category |= self::NO_SLASH;
        }

        if ($arg !== $secondParentFunction->args[1]) {
            return null;
        }

        // Annotate the node before returning it.
        $secondParentFunction->setAttribute(self::DIRROOT_WRANGLE_CATEGORY, $category);
        $secondParentFunction->setAttribute(self::DIRROOT_WRANGLE_VARIABLE_NODE, $secondParentFunction->args[0]->value);

        return $secondParentFunction;
    }

    /**
     * Which of the two nodes in a binary op is the other one?
     *
     * @param Node\Expr\BinaryOp $binaryOp the binary op
     * @param Node $node the node we have
     * @return Expr the other node
     * @throws Exception
     */
    private function getOtherExprInBinaryOp(Node\Expr\BinaryOp $binaryOp, Node $node): Node\Expr
    {
        if ($binaryOp->left === $node) {
            return $binaryOp->right;
        } elseif ($binaryOp->right === $node) {
            return $binaryOp->left;
        }
        throw new Exception("Node not found in binary op");
    }

    /**
     * @param FuncCall $functionCall
     * @param Node $pathNode
     * @return Expr\BinaryOp|null
     * @throws Exception
     */
    private function checkStrpos(FuncCall $functionCall, Node $pathNode): ?Expr\BinaryOp
    {
        if ($functionCall->name->toString() !== 'strpos') {
            throw new Exception("checkStrpos called with non-strpos function");
        }

        // We're only interested in our path node being the second argument to strpos().
        if (!(($functionCall->args[1]->value === $pathNode))) {
            return null;
        }

        $variableNode = $functionCall->args[0]->value;

        // Now we need to check that the strpos() is the argument to a binary comparison.
        $parentComparison = $functionCall->getAttribute('parent');
        if (!($parentComparison instanceof Node\Expr\BinaryOp)) {
            return null;
        }

        // Note that we don't really have to care about trailing slashes here
        // as it's either in the codebase or it isn't.
        $category = self::ABSOLUTE_PATH_IN_CODEBASE;

        $other = $this->getOtherExprInBinaryOp($parentComparison, $functionCall);

        $sigil = $parentComparison->getOperatorSigil();

        if (!($other instanceof Node\Scalar\LNumber && $other->value === 0)) {
            return null;
        }

        if ($sigil === '!==' || $sigil === '!=') {
            $category |= self::NEGATIVE;
        } elseif ($sigil !== '===' && $sigil !== '==') {
            // Unexpected comparison operator.
            return null;
        }

        $parentComparison->setAttribute(self::DIRROOT_WRANGLE_CATEGORY, $category);
        $parentComparison->setAttribute(self::DIRROOT_WRANGLE_VARIABLE_NODE, $variableNode);
        return $parentComparison;
    }

    /**
     * @param FuncCall $functionCall
     * @param Node $pathNode
     * @return FuncCall|null
     */
    private function checkStrReplace(FuncCall $functionCall, Node $pathNode): ?FuncCall
    {
        $resolvedInclude = $pathNode->getAttribute(PathResolvingVisitor::RESOLVED_INCLUDE);

        // Our path node must be the first argument to str_replace() (search).
        if ($functionCall->args[0]->value !== $pathNode) {
            return null;
        }
        if (!($functionCall->args[1]->value instanceof \PhpParser\Node\Scalar\String_)) {
            return null;
        }

        $variableNode = $functionCall->args[2]->value;

        /** @var \PhpParser\Node\Scalar\String_ $replacement */
        $replacement = $functionCall->args[1]->value;
        if ($replacement->value === '') {
            $category = self::ABSOLUTE_PATH_TO_RELATIVE;
            if ($resolvedInclude !== '@') {
                $category |= self::NO_SLASH;
            }
        } else {
            $category = self::REPLACE_WITH_STRING;
            if ($resolvedInclude !== '@') {
                $category |= self::NO_SLASH;
            }
            $functionCall->setAttribute(self::DIRROOT_WRANGLE_REPLACEMENT_NODE, $replacement);
        }

        // We need to be careful with str_replace() as it is subtly different from
        // substr(strlen($CFG->dirroot)) in that it will accept either absolute or relative
        // paths (i.e. if it doesn't start with dirroot it's left alone)
        $category |= self::ALLOW_RELATIVE_PATHS;

        $functionCall->setAttribute(self::DIRROOT_WRANGLE_CATEGORY, $category);
        $functionCall->setAttribute(self::DIRROOT_WRANGLE_VARIABLE_NODE, $variableNode);
        return $functionCall;
    }


}