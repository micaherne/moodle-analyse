<?php

namespace MoodleAnalyse\Codebase\Analyse;

use Symfony\Component\Finder\SplFileInfo;

class FakeSplFileInfo extends SplFileInfo
{
    /**
     * @inheritDoc
     */
    public function __construct(string $file, string $relativePath, string $relativePathname, private string $contents)
    {
        parent::__construct($file, $relativePath, $relativePathname);
    }

    /**
     * @inheritDoc
     */
    public function getContents(): string
    {
        return $this->contents;
    }


}