<?php

namespace MoodleAnalyse\Rewrite;

use MoodleAnalyse\Codebase\PathCode;

class GetComponentPathRewrite extends Rewrite
{

    public function __construct(PathCode $pathCode)
    {
        $pathComponent = $pathCode->getPathComponent();
        if (is_null($pathComponent)) {
            throw new \RuntimeException("Unable to rewrite to an unknown component");
        } elseif ($pathComponent === 'core_root') {
            throw new \RuntimeException("Unable to rewrite to the core root component");
        } elseif ($pathComponent === 'core_lib') {
            // The correct Moodle name for this component is 'core'.
            $pathComponent = 'core';
        }

        // TODO: This seems dumb, we've already checked it's not null.
        $pathComponentCode = is_null($pathComponent) ? 'null' : '"' . $pathComponent . '"';
        parent::__construct(
            $pathCode->getPathCodeStartFilePos(),
            $pathCode->getPathCodeEndFilePos(),
            '\core_component::get_component_path(' . $pathComponentCode . ', "' . $pathCode->getPathWithinComponent(
            ) . '")'
        );
    }
}