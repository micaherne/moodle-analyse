<?php

namespace MoodleAnalyse\Codebase\Rewrite;

use MoodleAnalyse\Codebase\Analyse\FileAnalysis;

interface Rewriter
{

    /**
     * Calculate changes to be made to the file based on its analysis and rewrite it in place.
     *
     * @param FileAnalysis $fileAnalysis
     * @return bool
     */
    public function rewrite(FileAnalysis $fileAnalysis): bool;
}