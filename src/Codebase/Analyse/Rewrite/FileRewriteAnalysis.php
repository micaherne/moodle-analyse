<?php

namespace MoodleAnalyse\Codebase\Analyse\Rewrite;

use MoodleAnalyse\Codebase\Analyse\FileAnalysis;

class FileRewriteAnalysis
{

    /**
     * @var array<CodebasePathRewriteAnalysis>
     */
    private array $codebasePathRewriteAnalyses = [];

    public function __construct(private FileAnalysis $fileAnalysis)
    {
    }

    public function addCodebasePathRewriteAnalysis(CodebasePathRewriteAnalysis $rewriteAnalysis)
    {
        $this->codebasePathRewriteAnalyses[] = $rewriteAnalysis;
    }

    public function getFileAnalysis(): FileAnalysis
    {
        return $this->fileAnalysis;
    }

    public function getCodebasePathRewriteAnalyses(): array
    {
        return $this->codebasePathRewriteAnalyses;
    }

    public function getRewrites()
    {
        $rewrites = [];
        foreach ($this->codebasePathRewriteAnalyses as $codebasePathRewriteAnalysis) {
            $rewrite = $codebasePathRewriteAnalysis->getRewrite();
            if ($rewrite !== null) {
                $rewrites[] = $rewrite;
            }
        }
        return $rewrites;
    }


}