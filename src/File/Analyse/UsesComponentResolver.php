<?php
declare(strict_types=1);

namespace MoodleAnalyse\File\Analyse;

use MoodleAnalyse\Codebase\ComponentResolver;

interface UsesComponentResolver
{
    public function setComponentResolver(ComponentResolver $componentResolver): void;
}