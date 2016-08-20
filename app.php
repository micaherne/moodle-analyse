<?php

$moodleroot = realpath(__DIR__ . '/moodle');
$uri = $_SERVER['REQUEST_URI'];

$p = strpos($uri, '.php');

// Use index.php as directory index
if ($p === false) {
    // Check URLs with no filename but query string e.g. /?redirect=0
    if (strpos($uri, '/?') !== false) {
        $uri = str_replace('/?', '/index.php?', $uri);
    } else if (substr($uri, -1) === '/') {
        $uri .= 'index.php';
    }
    $p = strpos($uri, '.php');
}

if ($p === false) {
    header("401 Not authorised");
    die;
}

$script = realpath($moodleroot . substr($uri, 0, $p + 4));
if (substr($uri, $p + 4, 1) === '/') {
    $_SERVER['PATH_INFO'] = substr($uri, $p + 4);
    $qs = strpos($_SERVER['PATH_INFO'], '?');
    if ($qs !== false) {
        $_SERVER['PATH_INFO'] = substr($_SERVER['PATH_INFO'], 0, $qs);
    }
}


$json = file_get_contents(__DIR__ . '/whitelist.json');
$whitelist = json_decode($json);

if (!isset($whitelist->$script)) {
    header("HTTP/1.0 401 Not authorised");
    die("Not authorised");
}

chdir(dirname($script));
include $script;
