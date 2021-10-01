<?php

namespace MoodleAnalyse\File\Index;

use MoodleAnalyse\Codebase\ComponentIdentifier;

interface UsesComponentIdentifier
{
    public function setComponentIdentifier(ComponentIdentifier $componentIdentifier): void;
}