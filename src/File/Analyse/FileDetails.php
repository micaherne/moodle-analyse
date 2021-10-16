<?php
declare(strict_types=1);

namespace MoodleAnalyse\File\Analyse;

use Symfony\Component\Finder\SplFileInfo;

class FileDetails
{
    private SplFileInfo $fileInfo;
    private string $contents;
    private string $component;

    /**
     * @param SplFileInfo $fileInfo
     * @param string $contents
     * @param string $component
     */
    public function __construct(SplFileInfo $fileInfo, string $contents, string $component)
    {
        $this->fileInfo = $fileInfo;
        $this->contents = $contents;
        $this->component = $component;
    }

    /**
     * @return SplFileInfo
     */
    public function getFileInfo(): SplFileInfo
    {
        return $this->fileInfo;
    }

    /**
     * @return string
     */
    public function getContents(): string
    {
        return $this->contents;
    }

    /**
     * @return string
     */
    public function getComponent(): string
    {
        return $this->component;
    }




}