<?php

namespace MoodleAnalyse\File\Analyse;

use MoodleAnalyse\Codebase\ComponentIdentifier;
use MoodleAnalyse\File\Index\BasicObjectIndex;
use PhpParser\NodeVisitor;
use Symfony\Component\Finder\SplFileInfo;

interface FileAnalyser
{
    /**
     * @return NodeVisitor[]
     */
    public function getNodeVisitors(): array;

    public function setFileDetails(FileDetails $fileDetails): void;

    public function getAnalysis(): array;

    /**
     * @return BasicObjectIndex[]
     */
    public function getIndexes(): array;
}