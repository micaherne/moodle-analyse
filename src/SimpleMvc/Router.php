<?php

namespace MoodleAnalyse\SimpleMvc;

class Router {

    private $moodleroot;
    private $configfile;

    private $whitelist;

    public function __construct($moodleroot, $configfile = null) {
        $this->moodleroot = $moodleroot;
        $this->configfile = $configfile;
    }

    public function setWhitelist($whitelist) {
        $this->whitelist = $whitelist;
    }

    public function route() {

        $uri = $_SERVER['REQUEST_URI'];

        $p = strpos($uri, '.php');

        // Use index.php as directory index
        if ($p === false) {
            // Check URLs with no filename but query string e.g. /?redirect=0
            if (strpos($uri, '/?') !== false) {
                $uri = str_replace('/?', '/index.php?', $uri);
            } else if (substr($uri, -1) === '/') {
                $uri .= 'index.php';
            } else {
                $uri .= '/index.php';
            }
            $p = strpos($uri, '.php');
        }

        $script = realpath($this->moodleroot . substr($uri, 0, $p + 4));

        if (!$script) {
            header("HTTP/1.0 401 Not authorised");
            die("Not authorised");
        }

        // Hack to prevent relative calls like file_exists() breaking.
        chdir(dirname($script));

        $controllerClass = '\\controller' . str_replace('/', '\\', substr($uri, 0, $p));

        if (substr($uri, $p + 4, 1) === '/') {
            $_SERVER['PATH_INFO'] = substr($uri, $p + 4);
            $qs = strpos($_SERVER['PATH_INFO'], '?');
            if ($qs !== false) {
                $_SERVER['PATH_INFO'] = substr($_SERVER['PATH_INFO'], 0, $qs);
            }
        }

        if (class_exists($controllerClass)) {
            if (is_null($this->configfile)) {
                $moodleConfig = new MoodleConfig($this->moodleroot . '/config.php');
            } else {
                $moodleConfig = new MoodleConfig($this->configfile);
            }

            $controller = new $controllerClass();
            $controller->run($moodleConfig);
            exit;
        }

        // We shouldn't get here as all pages have been converted to controllers.
        // if (!is_null($this->whitelist) && !isset($this->whitelist->$script)) {
            header("HTTP/1.0 401 Not authorised");
            die("Not authorised");
        //}

        chdir(dirname($script));
        include $script;
    }
}
