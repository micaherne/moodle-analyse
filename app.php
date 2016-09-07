<?php

require_once 'vendor/autoload.php';

$moodleroot = realpath(__DIR__ . '/moodle');

// This currently requires a whitelist.json file, which is only
// generated as a cache file by EntryPointIterator at the moment.
$json = file_get_contents(__DIR__ . '/whitelist.json');
$whitelist = json_decode($json);

$route = new \MoodleAnalyse\SimpleMvc\Router($moodleroot);
$route->setWhitelist($whitelist);

$route->route();
