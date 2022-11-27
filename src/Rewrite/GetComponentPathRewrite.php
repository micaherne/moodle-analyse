<?php

namespace MoodleAnalyse\Rewrite;

use MoodleAnalyse\Codebase\PathCode;

class GetComponentPathRewrite extends Rewrite
{

    public function __construct(PathCode $pathCode)
    {
        $pathComponent = $pathCode->getPathComponent();
        $pathComponentCode = is_null($pathComponent) ? 'null' : '"' . $pathComponent . '"';
        parent::__construct(
            $pathCode->getPathCodeStartFilePos(),
            $pathCode->getPathCodeEndFilePos(),
            '\core_component::get_component_path(' . $pathComponentCode . ', "' . $pathCode->getPathWithinComponent(
            ) . '")'
        );
    }
}