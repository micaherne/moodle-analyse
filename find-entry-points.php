<?php

require_once 'vendor/autoload.php';

$moodleroot = realpath(__DIR__ . '/moodle');

$entry = new \MoodleAnalyse\EntryPoint($moodleroot);

// print_r($files);
file_put_contents(__DIR__ . '/whitelist.json', json_encode($entry->findEntryPoints()));
