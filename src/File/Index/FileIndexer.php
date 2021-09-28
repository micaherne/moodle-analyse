<?php

namespace MoodleAnalyse\File\Index;

use PhpParser\NodeVisitor;
use Symfony\Component\Finder\SplFileInfo;

interface FileIndexer
{
    /**
     * @return NodeVisitor[]
     */
    public function getNodeVisitors(): array;

    public function setFile(SplFileInfo $file): void;

    public function setFileContents(string $fileContents): void;

    public function writeIndex(): void;
}