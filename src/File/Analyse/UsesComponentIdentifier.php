<?php

namespace MoodleAnalyse\File\Analyse;

use MoodleAnalyse\Codebase\ComponentIdentifier;

interface UsesComponentIdentifier
{
    public function setComponentIdentifier(ComponentIdentifier $componentIdentifier): void;
}