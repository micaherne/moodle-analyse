<?php

namespace MoodleAnalyse\Rewrite;

use MoodleAnalyse\Codebase\PathCode;

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