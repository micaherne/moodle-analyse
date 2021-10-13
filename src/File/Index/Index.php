<?php

namespace MoodleAnalyse\File\Index;

interface Index
{
    public function getSources();

    public function index($analysis, ?string $sourceClass = null);

    /**
     * @param string $indexDirectory
     */
    public function setIndexDirectory(string $indexDirectory): void;
}