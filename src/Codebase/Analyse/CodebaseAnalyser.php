<?php

declare(strict_types=1);

namespace MoodleAnalyse\Codebase\Analyse;

use MoodleAnalyse\Codebase\ComponentResolver;
use MoodleAnalyse\File\FileFinder;
use Symfony\Component\Finder\SplFileInfo;

class CodebaseAnalyser
{

    private readonly ComponentResolver $componentResolver;
    private readonly FileAnalyser $fileAnalyser;

    public function __construct(private readonly string $moodleDirectory)
    {
        $this->componentResolver = new ComponentResolver($this->moodleDirectory);
        $this->fileAnalyser = new FileAnalyser($this->componentResolver);
    }

    /**
     * @return iterable<FileAnalysis>
     * @throws \Exception
     */
    public function analyseAll(): iterable {
        $finder = new FileFinder($this->moodleDirectory);

        /** @var SplFileInfo $file */
        foreach ($finder->getFileIterator() as $file) {
            yield $this->fileAnalyser->analyseFile($file);
        }

    }
}