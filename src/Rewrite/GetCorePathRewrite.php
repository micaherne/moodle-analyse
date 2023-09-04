<?php

namespace MoodleAnalyse\Rewrite;

use MoodleAnalyse\Codebase\PathCode;
use MoodleAnalyse\Rewrite\Rewrite;

class GetCorePathRewrite extends Rewrite
{
    public function __construct(PathCode $pathCode)
    {
        $pathComponent = $pathCode->getPathComponent();

        if ($pathComponent !== 'core_root') {
            throw new \RuntimeException("Unable to rewrite to anything but core_root");
        }

        parent::__construct(
            $pathCode->getPathCodeStartFilePos(),
            $pathCode->getPathCodeEndFilePos(),
            '\core_component::get_core_path("' . $pathCode->getPathWithinComponent(
            ) . '")'
        );
    }
}