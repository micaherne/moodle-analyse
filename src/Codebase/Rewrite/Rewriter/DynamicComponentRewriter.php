<?php

namespace MoodleAnalyse\Codebase\Rewrite\Rewriter;

use MoodleAnalyse\Codebase\Analyse\FileAnalysis;
use MoodleAnalyse\Codebase\Rewrite\Rewriter;
use MoodleAnalyse\Rewrite\Rewrite;

/**
 * Rewrites paths with a variable for the component name.
 *
 * For example, "$CFG->dirroot/mod/$mod->name/lib.php" to core_component::get_component_path("mod_{$mod->name}", "lib.php")
 *
 *
 * @deprecated This is only for {@link \MoodleAnalyse\Console\Command\RewriteCommand} and will be removed.
 *
 */
class DynamicComponentRewriter extends AbstractRewriter implements Rewriter
{

    public function rewrite(FileAnalysis $fileAnalysis): bool
    {
        /** @var array<Rewrite> $rewrites */
        $rewrites = [];
        foreach ($fileAnalysis->getCodebasePaths() as $codebasePath) {
            $pathCode = $codebasePath->getPathCode();
            $pathComponent = $pathCode->getPathComponent();
            if (is_null($pathComponent)) {
                continue;
            }
            if (str_contains($pathComponent, '$')) {
                $rewrites[] = new Rewrite(
                    $pathCode->getPathCodeStartFilePos(),
                    $pathCode->getPathCodeEndFilePos(),
                    '\core_component::get_component_path("' . $pathComponent . '", "' . $pathCode->getPathWithinComponent(
                    ) . '")'
                );
            }
        }

        if ($rewrites === []) {
            return false;
        }

        $this->applyRewrites($rewrites, $fileAnalysis->getFinderFile());

        return true;
    }

}