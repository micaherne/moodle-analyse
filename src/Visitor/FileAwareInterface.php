<?php

declare(strict_types=1);

namespace MoodleAnalyse\Visitor;

use Symfony\Component\Finder\SplFileInfo;

interface FileAwareInterface
{
    public function setFile(SplFileInfo $file): void;
}