<?php

use MoodleAnalyse\Codebase\ComponentIdentifier;
use MoodleAnalyse\File\Analyse\Engine;
use MoodleAnalyse\File\FileFinder;
use MoodleAnalyse\File\Analyse\FileDetails;
use MoodleAnalyse\File\Analyse\FileAnalyser;
use MoodleAnalyse\File\Analyse\FunctionDefinitionAnalyser;
use MoodleAnalyse\File\Analyse\IncludeAnalyser;
use MoodleAnalyse\File\Analyse\UsesComponentIdentifier;
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

$engine = new Engine($moodleroot, $indexDirectory);

$engine->execute();