<?php

use MoodleAnalyse\File\Analyse\Engine;
use MoodleAnalyse\File\Index\BasicObjectIndex;

require_once __DIR__ . '/../vendor/autoload.php';

$indexDirectory = __DIR__ . '/../indexes';
if (!is_dir($indexDirectory)) {
    mkdir($indexDirectory);
}

$moodleroot = __DIR__ . '/../moodle';

$engine = new Engine($moodleroot, $indexDirectory);

$engine->execute();