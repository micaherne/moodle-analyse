<?php

declare(strict_types=1);

namespace MoodleAnalyse\Codebase\Analyse;

use MoodleAnalyse\Codebase\CodebasePath;
use Symfony\Component\Finder\SplFileInfo;

class FileAnalysis
{

    /** @var array<int, CodebasePath> */
    private array $codebasePaths = [];

    private ?bool $includesConfig = null;

    public function __construct(private SplFileInfo $finderFile, private ?string $fileComponent)
    {
    }

    public function addCodebasePath(CodebasePath $codebasePath): void
    {
        $this->codebasePaths[] = $codebasePath;
    }

    public function getRelativePath(): string {
        return str_replace('\\', '/', $this->finderFile->getRelativePathname());
    }

    public function getCodebasePaths(): array
    {
        return $this->codebasePaths;
    }

    public function getFinderFile(): SplFileInfo
    {
        return $this->finderFile;
    }

    public function getFileComponent(): ?string
    {
        return $this->fileComponent;
    }

    public function setIncludesConfig(bool $includesConfig)
    {
        $this->includesConfig = $includesConfig;
    }

    public function getIncludesConfig(): ?bool
    {
        return $this->includesConfig;
    }




}