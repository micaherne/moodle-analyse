<?php

use MoodleAnalyse\Codebase\ResolvedPathProcessor;
use MoodleAnalyse\Visitor\PathFindingVisitor;
use MoodleAnalyse\Visitor\PathResolvingVisitor;
use PhpParser\Lexer;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\ParserFactory;

require_once __DIR__ . '/../vendor/autoload.php';

$finder = newFileFinder(__DIR__ . '/../moodle');

$lexer = new Lexer(['usedAttributes' => ['startLine', 'endLine', 'startFilePos', 'endFilePos']]);
$parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7, $lexer);

// $nodes = $parser->parse("<?php \$file = PATH_SEPARATOR;");
$preProcessTraverser = new NodeTraverser();
$preProcessTraverser->addVisitor(new NameResolver());
$preProcessTraverser->addVisitor(new ParentConnectingVisitor());
$preProcessTraverser->addVisitor(new PathFindingVisitor());

$processTraverser = new NodeTraverser();
$processTraverser->addVisitor(new NameResolver());
$processTraverser->addVisitor(new ParentConnectingVisitor());
$pathResolvingVisitor = new PathResolvingVisitor();
$processTraverser->addVisitor($pathResolvingVisitor);

$resolvedIncludeProcessor = new ResolvedPathProcessor();

$out = fopen(__DIR__ . '/../relative-paths.csv', 'w');
if ($out === false) {
    die("Unable to open CSV\n");
}

fputcsv($out, ['Relative filename', 'Path start line', 'Path end line', 'Path code', 'Resolved include',
    'Parent code', 'Parent start line', 'Parent end line', 'Parent function call', 'Config include?', '$CFG available', 'Category', 'Rewrite code', 'From core component',
    'Assigned from previous path var']);
/** @var \Symfony\Component\Finder\SplFileInfo $file */
foreach ($finder->getFileIterator() as $file) {
    $relativePathname = str_replace('\\', '/', $file->getRelativePathname());

    $pathResolvingVisitor->setFilePath($relativePathname);
    echo $relativePathname . "\n";
    $contents = $file->getContents();
    $nodes = $parser->parse($contents);
    $nodes = $preProcessTraverser->traverse($nodes);

    $nodes = $processTraverser->traverse($nodes);
    $pathNodes = $pathResolvingVisitor->getPathNodes();
    foreach ($pathNodes as $pathNode) {
        $code = substr($contents, $pathNode->getStartFilePos(), $pathNode->getEndFilePos() - $pathNode->getStartFilePos() + 1);
        $resolvedInclude = $pathNode->getAttribute('resolvedInclude');

        echo $pathNode->getStartLine();
        if ($pathNode->getEndLine() !== $pathNode->getStartLine()) {
            echo '-' . $pathNode->getEndLine();
        }
        echo ': ';
        echo $code;
        echo ': ' . $resolvedInclude;
        echo "\n";

        $outputRow = [$relativePathname, $pathNode->getStartLine(), $pathNode->getEndLine(), $code, $resolvedInclude];
        if ($pathNode->hasAttribute(PathResolvingVisitor::CONTAINING_EXPRESSION)) {
            /** @var Node\Expr $parentNode */
            $parentNode = $pathNode->getAttribute(PathResolvingVisitor::CONTAINING_EXPRESSION);
            $parentCode = substr($contents, $parentNode->getStartFilePos(), $parentNode->getEndFilePos() - $parentNode->getStartFilePos() + 1);
            $outputRow = array_merge($outputRow, [$parentCode, $parentNode->getStartLine(), $parentNode->getEndLine()]);

            if ($parentNode instanceof Node\Expr\FuncCall) {
                $outputRow[] = $parentNode->name->toCodeString();
            } else {
                $outputRow[] = '';
            }

            $outputRow[] = $parentNode->getAttribute(PathResolvingVisitor::IS_CONFIG_INCLUDE) ? 'config include' : '';

            // Don't hold a reference to the node.
            unset($parentNode);
        } else {
            // Pad the row if we don't have a parent expression (usually it's a return statement)
            $outputRow = array_merge($outputRow, array_fill(count($outputRow), 5, ''));
        }

        $cfgAvailable = $pathNode->getAttribute(PathResolvingVisitor::CFG_AVAILABLE);
        $outputRow[] = $cfgAvailable ?? '';

        $category = $resolvedIncludeProcessor->categorise($resolvedInclude);
        $outputRow[] = $category ?? '';

        $codeString = $resolvedIncludeProcessor->toCodeString($resolvedInclude);
        $outputRow[] = $codeString ?? '';

        $outputRow[] = is_null($pathNode->getAttribute(PathResolvingVisitor::FROM_CORE_COMPONENT)) ? '' : 'yes';
        $outputRow[] = is_null($pathNode->getAttribute(PathResolvingVisitor::ASSIGNED_FROM_PATH_VAR)) ? '' : 'yes';

        fputcsv($out, $outputRow);
    }
}

fclose($out);