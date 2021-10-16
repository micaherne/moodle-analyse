<?php
declare(strict_types=1);

namespace MoodleAnalyse\File\Analyse;

use MoodleAnalyse\Codebase\ComponentIdentifier;

interface UsesComponentIdentifier
{
    public function setComponentIdentifier(ComponentIdentifier $componentIdentifier): void;
}