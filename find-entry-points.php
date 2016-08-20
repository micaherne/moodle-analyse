<?php

$moodleroot = realpath(__DIR__ . '/moodle');

setUpGlobals($moodleroot);

// core directories
// lib has some entry points like requirejs.php
$exclude[realpath($moodleroot . '/lib/tests')] = true;
$exclude[realpath($moodleroot . '/backup/cc')] = true;
$exclude[realpath($moodleroot . '/backup/controller')] = true;
$exclude[realpath($moodleroot . '/backup/converter')] = true;
$exclude[realpath($moodleroot . '/backup/util')] = true;
$exclude[realpath($moodleroot . '/pix')] = true;
$exclude[realpath($moodleroot . '/lang')] = true;
$exclude[realpath($moodleroot . '/vendor')] = true;
$exclude[realpath($moodleroot . '/install/lang')] = true;
$exclude[realpath($moodleroot . '/admin/settings')] = true;
$exclude[realpath($moodleroot . '/user/filters')] = true;

// test directories from phpunit.xml.dist
$dom = new \DOMDocument();
$dom->load($moodleroot . '/phpunit.xml.dist');
foreach($dom->getElementsByTagName('directory') as $node) {
    $exclude[realpath($moodleroot . '/' . $node->textContent)] = true;
}

// plugin directories
$exclude[realpath($moodleroot . '/auth/cas/CAS')] = true;

$plugintypes = core_component::get_plugin_types();
foreach ($plugintypes as $type => $dir) {

    $plugins = core_component::get_plugin_list($type);
    foreach ($plugins as $name => $dir) {
        if (!empty($dir) && file_exists($dir)) {
            // TODO: Remove: testing only
            // $exclude[realpath($dir)] = true;

            $exclude[realpath($dir . '/amd')] = true;
            $exclude[realpath($dir . '/backup')] = true;
            $exclude[realpath($dir . '/behat')] = true;
            $exclude[realpath($dir . '/classes')] = true;
            $exclude[realpath($dir . '/cli')] = true;
            $exclude[realpath($dir . '/db')] = true;
            $exclude[realpath($dir . '/tests')] = true;
            $exclude[realpath($dir . '/jquery')] = true;
            $exclude[realpath($dir . '/lang')] = true;
            $exclude[realpath($dir . '/pix')] = true;
            $exclude[realpath($dir . '/yui')] = true;
            $exclude[realpath($dir . '/version.php')] = true;
            $exclude[realpath($dir . '/renderer.php')] = true;
            $exclude[realpath($dir . '/lib.php')] = true;
            $exclude[realpath($dir . '/locallib.php')] = true;
            $exclude[realpath($dir . '/settings.php')] = true;
        }

        if ($type == 'theme') {
            $exclude[realpath($dir . '/layout')] = true;
            $exclude[realpath($dir . '/config.php')] = true;
        }

        if ($type == 'datafield') {
            $exclude[realpath($dir . '/field.class.php')] = true;
        }
    }
}

// Exclude subsystem tests etc
$subsystems = core_component::get_core_subsystems();
foreach ($subsystems as $name => $dir) {
    if (!empty($dir) && file_exists($dir)) {
        $exclude[realpath($dir . '/amd')] = true;
        $exclude[realpath($dir . '/backup')] = true;
        $exclude[realpath($dir . '/behat')] = true;
        $exclude[realpath($dir . '/classes')] = true;
        $exclude[realpath($dir . '/cli')] = true;
        $exclude[realpath($dir . '/db')] = true;
        $exclude[realpath($dir . '/tests')] = true;
        $exclude[realpath($dir . '/jquery')] = true;
        $exclude[realpath($dir . '/lang')] = true;
        $exclude[realpath($dir . '/pix')] = true;
        $exclude[realpath($dir . '/yui')] = true;
        $exclude[realpath($dir . '/version.php')] = true;
        $exclude[realpath($dir . '/renderer.php')] = true;
        $exclude[realpath($dir . '/lib.php')] = true;
        $exclude[realpath($dir . '/locallib.php')] = true;

        // settings.php is a real page script in admin only
        if ($name !== 'admin') {
            $exclude[realpath($dir . '/settings.php')] = true;
        }
    }
}

// Preload with known exceptions
$files = [
    // files required by install
    realpath($moodleroot . '/install.php') => true,
    realpath($moodleroot . '/install/css.php') => true,

    // wrapper around /ajax/service.php
    realpath($moodleroot . '/lib/ajax/service-nologin.php') => true
];

$classdefiningfiles = [];

findPhpFiles($moodleroot, $files, $exclude);
// print_r($files);
file_put_contents(__DIR__ . '/whitelist.json', json_encode($files));

exit;

$controllersdir = __DIR__ . '/controllers';

foreach ($files as $file) {
    $classfile = $controllersdir . substr($file, strlen($moodleroot));
    if (!file_exists(dirname($classfile))) {
        mkdir(dirname($classfile), null, true);
    }
    $fullclassname = '\controller' . substr($file, strlen($moodleroot), -4);

    $classnameparts = explode('\\', $fullclassname);
    $classname = array_pop($classnameparts);

    $namespace = implode('\\', $classnameparts);

    $code = "<?php namespace $namespace;\n    class $classname {";
    $code .= "\n        public function show() {\n            global \$CFG, \$DB, \$PAGE, \$OUTPUT; ";
    $code .= "\n            ";
    foreach(token_get_all(file_get_contents($file)) as $token) {
        if (is_array($token)) {
            if ($token[0] === T_OPEN_TAG || $token[0] === T_CLOSE_TAG) {
                continue;
            } else {
                // TODO: This will break heredocs and multiline string literals
                $code .= str_replace("\n", "\n            ", $token[1]);
            }
        } else {
            $code .= $token;
        }
    }
    $code .= "\n    }\n}";
    file_put_contents($classfile, $code);
}

function findPhpFiles($dir, &$files, $exclude) {

    echo "Checking $dir\n";
    foreach (new DirectoryIterator($dir) as $fileInfo) {

        if ($fileInfo->isDot()) {
            continue;
        }

        $path = realpath($fileInfo->getPathname());
        if (array_key_exists($path, $exclude)) {
            echo "Ignoring $path\n";
            continue;
        }

        if ($fileInfo->isDir()) {
            findPhpFiles($path, $files, $exclude);
        } else {
            if($fileInfo->getExtension() != 'php') {
                continue;
            }

            $content = file_get_contents($path);
            if (strpos($content, 'MOODLE_INTERNAL') !== false) {
                continue;
            }

            // Ignore files without a require config.php statement.
            // By definition, these are not entry points.
            if (!preg_match('/require.*config\.php/', $content)) {
                continue;
            }

            // Ignore files where a class is defined. This is a bit of a
            // rule of thumb, but excludes 20-odd files that all appear
            // to be libraries and not pages.
            // [UPDATE] Not necessary now we have check for config.php
            /* foreach(token_get_all($content) as $token) {
                if ($token[0] === T_CLASS) {
                    continue 2;
                }
            }*/

            $files[$path] = true;
        }

    }
}

function setUpGlobals($moodledir) {
    global $CFG, $DB;
    $CFG = new \stdClass();
    $CFG->dirroot = $moodledir;
    $CFG->dataroot = sys_get_temp_dir();
    $CFG->wwwroot = 'http://example.com';
    $CFG->debug = E_ALL;
    $CFG->debugdisplay = 1;
    define('CLI_SCRIPT', true);
    define('ABORT_AFTER_CONFIG', true); // We need just the values from config.php.
    define('CACHE_DISABLE_ALL', true); // This prevents reading of existing caches.
    define('IGNORE_COMPONENT_CACHE', true);
    require_once($CFG->dirroot . '/lib/setup.php');
}
