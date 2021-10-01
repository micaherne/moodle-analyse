<?php

namespace MoodleAnalyse\File\Index;

use MoodleAnalyse\Codebase\ComponentIdentifier;
use PhpParser\NodeVisitor;
use Symfony\Component\Finder\SplFileInfo;

interface FileIndexer
{
    /**
     * @return NodeVisitor[]
     */
    public function getNodeVisitors(): array;

    public function setFileDetails(FileDetails $fileDetails): void;

    public function writeIndex(): void;
}