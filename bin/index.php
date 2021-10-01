<?php

use MoodleAnalyse\File\FileFinder;
use MoodleAnalyse\File\Index\FileIndexer;
use MoodleAnalyse\File\Index\FunctionDefinitionIndexer;
use MoodleAnalyse\File\Index\IncludeIndexer;
use MoodleAnalyse\Visitor\IncludeResolvingVisitor;
use PhpParser\Lexer;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;

require_once __DIR__ . '/../vendor/autoload.php';

$indexDirectory = __DIR__ . '/../indexes';
if (!is_dir($indexDirectory)) {
    mkdir($indexDirectory);
}

$fileFinder = new FileFinder(__DIR__ . '/../moodle');

$lexer = new Lexer(['usedAttributes' => ['startFilePos', 'endFilePos']]);
$parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7, $lexer);
$traverser = new NodeTraverser();
$traverser->addVisitor(new NameResolver());
$traverser->addVisitor(new ParentConnectingVisitor());

$functionDefinitionIndexer = new FunctionDefinitionIndexer();
$includeIndexer = new IncludeIndexer();

/**
 * @var FileIndexer[]
 */
$indexers = [$functionDefinitionIndexer, $includeIndexer];

/** @var FileIndexer $indexer */
foreach ($indexers as $indexer) {
    foreach ($indexer->getNodeVisitors() as $visitor) {
        $traverser->addVisitor($visitor);
    }
}

/** @var \Symfony\Component\Finder\SplFileInfo $file */
foreach ($fileFinder->getFileIterator() as $file) {
    $fileContents = $file->getContents();

    foreach ($indexers as $indexer) {
        $indexer->setFile($file);
        $indexer->setFileContents($fileContents);
    }

    $nodes = $parser->parse($fileContents);
    $n = $traverser->traverse($nodes);

    foreach ($indexers as $indexer) {
        $indexer->writeIndex();
    }
}