<?php

namespace MoodleAnalyse\Rewrite;

use MoodleAnalyse\Codebase\PathCode;

/**
 * Rewrite a path to a component to use core_component::get_path_from_relative().
 *
 * This is only intended for use when the path code is a single variable (or function call etc)
 * that is relative to the component root. For example: @/{$path}.
 */
class GetPathFromRelativeRewrite extends Rewrite
{
    public function __construct(PathCode $pathCode)
    {
        $newPath = trim(ltrim($pathCode->getResolvedPath(), '@\\/'), '{}');
        $newCode = '\core_component::get_path_from_relative(' . $newPath . ')';
        parent::__construct(
            $pathCode->getPathCodeStartFilePos(),
            $pathCode->getPathCodeEndFilePos(),
            $newCode
        );
    }
}