<?php

require_once 'vendor/autoload.php';

$moodleroot = realpath(__DIR__ . '/moodle');

$json = file_get_contents(__DIR__ . '/whitelist.json');
$whitelist = json_decode($json);

$route = new \MoodleAnalyse\Router($moodleroot);
$route->setWhitelist($whitelist);

$route->route();
