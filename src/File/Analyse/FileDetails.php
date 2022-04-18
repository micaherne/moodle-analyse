<?php
declare(strict_types=1);

namespace MoodleAnalyse\File\Analyse;

use Symfony\Component\Finder\SplFileInfo;

class FileDetails
{
    private SplFileInfo $fileInfo;
    private string $contents;
    private string $component;
    private string $pathWithinComponent;

    /**
     * @param SplFileInfo $fileInfo
     * @param string $contents
     * @param string $component
     * @param string $pathWithinComponent
     */
    public function __construct(SplFileInfo $fileInfo, string $contents, string $component, string $pathWithinComponent)
    {
        $this->fileInfo = $fileInfo;
        $this->contents = $contents;
        $this->component = $component;
        $this->pathWithinComponent = $pathWithinComponent;
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

    /**
     * @return string
     */
    public function getPathWithinComponent(): string
    {
        return $this->pathWithinComponent;
    }




}