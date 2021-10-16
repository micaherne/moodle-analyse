<?php
declare(strict_types=1);

namespace MoodleAnalyse\File\Analyse;

use MoodleAnalyse\File\Index\BasicObjectIndex;
use PhpParser\NodeVisitor;

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