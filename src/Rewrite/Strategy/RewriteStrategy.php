<?php

declare(strict_types=1);

namespace MoodleAnalyse\Rewrite\Strategy;

use MoodleAnalyse\Rewrite\Rewrite;
use PhpParser\NodeVisitor;
use Symfony\Component\Finder\SplFileInfo;

interface RewriteStrategy
{

    /**
     * An array of lists of visitors, one list per processing pass required.
     *
     * @return NodeVisitor[][]
     */
    public function getVisitors(): array;

    /**
     * Get rewrites (array of start char no, end char no, code).
     *
     * @return Rewrite[]
     */
    public function getRewrites(array $nodes, string $fileContents, string $relativeFilePath): array;

    /**
     * An array of log type => array of entries.
     *
     * First entry should always be the line number.
     *
     * @return array[]
     */
    public function getCurrentFileLogData(): array;

    /**
     * Add any new files required.
     *
     * @param string $moodleroot
     */
    public function addFiles(string $moodleroot): void;

}