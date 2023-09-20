<?php

declare(strict_types=1);

namespace MoodleAnalyse\Codebase\Analyse;

use Exception;
use MoodleAnalyse\Codebase\ComponentResolver;
use MoodleAnalyse\File\FileFinder;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * This is a helper class which just sets up the other classes to do the analysis.
 */
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
     * @throws Exception
     */
    public function analyseAll(): iterable
    {
        $finder = new FileFinder($this->moodleDirectory);

        /** @var SplFileInfo $file */
        foreach ($finder->getFileIterator() as $file) {
            yield $this->fileAnalyser->analyseFile($file);
        }
    }

    /**
     * @param string $singleFile the path to the file, relative to the Moodle directory.
     * @return iterable<FileAnalysis>
     * @throws Exception
     */
    public function analyseFile(string $singleFile): iterable
    {
        if (!is_file($this->moodleDirectory . '/' . $singleFile)) {
            throw new Exception("File does not exist: {$singleFile}");
        }

        $file = new SplFileInfo($this->moodleDirectory . '/' . $singleFile, dirname($singleFile), $singleFile);

        yield $this->fileAnalyser->analyseFile($file);

    }
}