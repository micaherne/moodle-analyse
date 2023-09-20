<?php

namespace MoodleAnalyse\Rewrite;

use MoodleAnalyse\Codebase\PathCode;
use MoodleAnalyse\Rewrite\Rewrite;

/**
 * Rewrite a path to a component to use core_component::get_core_path().
 *
 * This is necessary as a) there is no component to pass to get_component_path and b) we can't just
 * use $CFG->dirroot as we have no idea if $CFG is in scope generally.
 *
 */
class GetCorePathRewrite extends Rewrite
{
    public function __construct(PathCode $pathCode)
    {
        $pathComponent = $pathCode->getPathComponent();

        if ($pathComponent !== 'core_root') {
            throw new \RuntimeException("Unable to rewrite to anything but core_root");
        }

        $pathInComponentCode = $this->toCodeString($pathCode->getPathWithinComponent());

        parent::__construct(
            $pathCode->getPathCodeStartFilePos(),
            $pathCode->getPathCodeEndFilePos(),
            '\core_component::get_core_path(' . $pathInComponentCode . ')'
        );
    }
}