<?php

use MoodleAnalyse\Codebase\ComponentIdentifier;
use MoodleAnalyse\File\FileFinder;
use MoodleAnalyse\File\Index\FileDetails;
use MoodleAnalyse\File\Index\FileIndexer;
use MoodleAnalyse\File\Index\FunctionDefinitionIndexer;
use MoodleAnalyse\File\Index\IncludeIndexer;
use MoodleAnalyse\File\Index\UsesComponentIdentifier;
use PhpParser\Lexer;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\ParserFactory;

require_once __DIR__ . '/../vendor/autoload.php';

$indexDirectory = __DIR__ . '/../indexes';
if (!is_dir($indexDirectory)) {
    mkdir($indexDirectory);
}

$moodleroot = __DIR__ . '/../moodle';

$fileFinder = new FileFinder($moodleroot);

$lexer = new Lexer(['usedAttributes' => ['startFilePos', 'endFilePos']]);
$parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7, $lexer);
$traverser = new NodeTraverser();
$traverser->addVisitor(new NameResolver());
$traverser->addVisitor(new ParentConnectingVisitor());

$functionDefinitionIndexer = new FunctionDefinitionIndexer();
$includeIndexer = new IncludeIndexer();

$componentIdentifier = new ComponentIdentifier($moodleroot);

/**
 * @var FileIndexer[]
 */
$indexers = [$functionDefinitionIndexer, $includeIndexer];

/** @var FileIndexer $indexer */
foreach ($indexers as $indexer) {
    if ($indexer instanceof UsesComponentIdentifier) {
        $indexer->setComponentIdentifier($componentIdentifier);
    }
    foreach ($indexer->getNodeVisitors() as $visitor) {
        $traverser->addVisitor($visitor);
    }
}

/** @var \Symfony\Component\Finder\SplFileInfo $file */
foreach ($fileFinder->getFileIterator() as $file) {
    $fileContents = $file->getContents();
    $component = $componentIdentifier->fileComponent($file->getRelativePathname());

    $fileDetails = new FileDetails($file, $fileContents, $component);

    foreach ($indexers as $indexer) {
        $indexer->setFileDetails($fileDetails);
    }

    $nodes = $parser->parse($fileContents);
    $n = $traverser->traverse($nodes);

    foreach ($indexers as $indexer) {
        $indexer->writeIndex();
    }
}