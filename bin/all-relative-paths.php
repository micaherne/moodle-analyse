<?php

use MoodleAnalyse\File\FileFinder;
use MoodleAnalyse\Visitor\PathFindingVisitor;
use MoodleAnalyse\Visitor\PathResolvingVisitor;
use PhpParser\Lexer;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;

require_once __DIR__ . '/../vendor/autoload.php';

$finder = new FileFinder(__DIR__ . '/../moodle');

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

$out = fopen(__DIR__ . '/../relative-paths.csv', 'w');

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
        fputcsv($out, $outputRow);
    }
}

fclose($out);