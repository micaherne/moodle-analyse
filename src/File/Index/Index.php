<?php
declare(strict_types=1);

namespace MoodleAnalyse\File\Index;

interface Index
{
    public function getSources();

    public function index($analysis, ?string $sourceClass = null);

    public function reset();

    /**
     * @param string $indexName
     */
    public function setIndexDirectory(string $indexDirectory): void;
}