<?php

namespace MoodleAnalyse\Rewrite;

use MoodleAnalyse\Codebase\CodebasePath;
use RuntimeException;

/**
 * Rewrite a path to a component to use a __DIR__ relative path instead of $CFG->dirroot or libdir.
 *
 * This simplifies the rewriting of paths within components as there's no real point using
 * get_component_path() when we know the two files will always be in the same place relative to
 * each other.
 */
class RelativeDirPathRewrite extends Rewrite
{

    public function __construct(CodebasePath $codebasePath, string $componentPath)
    {
        $resolvedFilename = '@/' . $codebasePath->getRelativeFilename();
        $pathCode = $codebasePath->getPathCode();
        $resolvedTarget = $pathCode->getResolvedPath();
        $componentPathScoped = '@/' . $componentPath;

        if (!str_starts_with($resolvedFilename, $componentPathScoped) || !str_starts_with(
                $resolvedTarget,
                $componentPathScoped
            )) {
            throw new RuntimeException("Source file and target must be inside $componentPath");
        }

        $resolvedFromRoot = substr($resolvedFilename, strlen($componentPath));
        $targetFromRoot = substr($resolvedTarget, strlen($componentPath));

        // Must be double quotes as it may contain variables.
        parent::__construct(
            $pathCode->getPathCodeStartFilePos(),
            $pathCode->getPathCodeEndFilePos(),
            '__DIR__ . "/' . self::calculateRelativePath($resolvedFromRoot, $targetFromRoot) . '"'
        );
    }

    public static function calculateRelativePath(string $sourceFilename, string $targetFilename): string
    {
        $sourceParts = explode('/', $sourceFilename);
        $targetParts = explode('/', $targetFilename);
        while ($sourceParts !== [] && $targetParts !== [] && $sourceParts[0] === $targetParts[0]) {
            array_shift($targetParts);
            array_shift($sourceParts);
        }
        $resultParts = $sourceParts !== [] ? array_fill(0, count($sourceParts) - 1, '..') : ['..'];
        $resultParts = array_merge($resultParts, $targetParts);
        return implode('/', $resultParts);
    }

}