<?php

namespace MoodleAnalyse\Codebase\Rewrite\Rewriter;

use MoodleAnalyse\Codebase\Analyse\FileAnalysis;
use MoodleAnalyse\Codebase\PathCategory;
use MoodleAnalyse\Codebase\Rewrite\Rewriter;
use MoodleAnalyse\Rewrite\GetComponentPathRewrite;
use MoodleAnalyse\Rewrite\Rewrite;

class SimplePathsFromCoreToNonCoreRewriter extends AbstractRewriter implements Rewriter
{

    /**
     * @inheritDoc
     */
    public function rewrite(FileAnalysis $fileAnalysis): bool
    {
        /** @var array<Rewrite> $rewrites */
        $rewrites = [];
        foreach ($fileAnalysis->getCodebasePaths() as $codebasePath) {
            if ($codebasePath->getPathCategory() !== PathCategory::SimpleFile) {
                continue;
            }
            if (!str_starts_with($fileAnalysis->getFileComponent(), 'core_')) {
                continue;
            }
            $targetComponent = $codebasePath->getPathCode()->getPathComponent();

            // Need to check core as getPathComponent returns lib/ as core. Also null as dirroot returns null.
            if (is_null($targetComponent) || $targetComponent === 'core' || str_starts_with($targetComponent, 'core_')) {
                continue;
            }
            $rewrites[] = new GetComponentPathRewrite($codebasePath->getPathCode());
        }
        if (count($rewrites) === 0) {
            return false;
        }

        $this->applyRewrites($rewrites, $fileAnalysis->getFinderFile());

        return true;
    }
}