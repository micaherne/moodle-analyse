<?php

namespace MoodleAnalyse\Codebase\Rewrite\Rewriter;

use MoodleAnalyse\Codebase\Analyse\FileAnalysis;
use MoodleAnalyse\Codebase\PathCategory;
use MoodleAnalyse\Codebase\Rewrite\Rewriter;
use MoodleAnalyse\Rewrite\Rewrite;

/**
 * @deprecated This is only for {@link \MoodleAnalyse\Console\Command\RewriteCommand} and will be removed.
 */
class FullRelativePathRewriter extends AbstractRewriter implements Rewriter
{

    public function rewrite(FileAnalysis $fileAnalysis): bool
    {
        /** @var array<Rewrite> $rewrites */
        $rewrites = [];
        foreach ($fileAnalysis->getCodebasePaths() as $codebasePath) {
            if ($codebasePath->getPathCategory() !== PathCategory::FullRelativePath) {
                continue;
            }
            $pathCode = $codebasePath->getPathCode();
            $newPath = trim(ltrim($pathCode->getResolvedPath(), '@\\/'), '{}');
            $newCode = '\core_component::get_path_from_relative(' . $newPath . ')';
            $rewrites[] = new Rewrite(
                $pathCode->getPathCodeStartFilePos(),
                $pathCode->getPathCodeEndFilePos(),
                $newCode
            );

        }

        if ($rewrites === []) {
            return false;
        }

        $this->applyRewrites($rewrites, $fileAnalysis->getFinderFile());

        return true;
    }
}