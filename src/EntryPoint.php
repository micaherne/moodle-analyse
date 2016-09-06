<?php

namespace MoodleAnalyse;

class EntryPoint {

    private $moodleroot;

    public function __construct($moodleroot) {
        $this->moodleroot = $moodleroot;
        \MoodleAnalyse\Fake\CoreComponent::init($this->moodleroot);
    }

    public function findEntryPoints() {
        $exclude = $this->excludedDirs();

        // Preload with known exceptions
        $files = [
            // files required by install
            realpath($this->moodleroot . '/install.php') => true,
            realpath($this->moodleroot . '/install/css.php') => true,

            // wrapper around /ajax/service.php
            realpath($this->moodleroot . '/lib/ajax/service-nologin.php') => true
        ];

        $this->iterateFiles($this->moodleroot, $files, $exclude);

        return $files;
    }

    function iterateFiles($dir, &$files, $exclude) {

        echo "Checking $dir\n";
        foreach (new \DirectoryIterator($dir) as $fileInfo) {

            if ($fileInfo->isDot()) {
                continue;
            }

            $path = realpath($fileInfo->getPathname());
            if (array_key_exists($path, $exclude)) {
                echo "Ignoring $path\n";
                continue;
            }

            if ($fileInfo->isDir()) {
                $this->iterateFiles($path, $files, $exclude);
            } else {
                if($fileInfo->getExtension() != 'php') {
                    continue;
                }

                $content = file_get_contents($path);
                if (strpos($content, 'MOODLE_INTERNAL') !== false) {
                    continue;
                }

                if (preg_match('/define(.*CLI_SCRIPT.*,.*(true|1))/', $content)) {
                    continue;
                }

                // Ignore files without a require config.php statement.
                // With a few exceptions, these are not entry points.
                if (!preg_match('/require.*\bconfig\.php\b/', $content)) {
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

    public function excludedDirs() {
        $exclude = [];

        // core directories
        // lib has some entry points like requirejs.php
        $exclude[realpath($this->moodleroot . '/lib/tests')] = true;
        $exclude[realpath($this->moodleroot . '/backup/cc')] = true;
        $exclude[realpath($this->moodleroot . '/backup/controller')] = true;
        $exclude[realpath($this->moodleroot . '/backup/converter')] = true;
        $exclude[realpath($this->moodleroot . '/backup/util')] = true;
        $exclude[realpath($this->moodleroot . '/pix')] = true;
        $exclude[realpath($this->moodleroot . '/lang')] = true;
        $exclude[realpath($this->moodleroot . '/vendor')] = true;
        $exclude[realpath($this->moodleroot . '/install/lang')] = true;
        $exclude[realpath($this->moodleroot . '/admin/settings')] = true;
        $exclude[realpath($this->moodleroot . '/user/filters')] = true;

        // test directories from phpunit.xml.dist
        $dom = new \DOMDocument();
        $dom->load($this->moodleroot . '/phpunit.xml.dist');
        foreach($dom->getElementsByTagName('directory') as $node) {
            $exclude[realpath($this->moodleroot . '/' . $node->textContent)] = true;
        }

        // plugin directories
        $exclude[realpath($this->moodleroot . '/auth/cas/CAS')] = true;

        $plugintypes = \core_component::get_plugin_types();
        foreach ($plugintypes as $type => $dir) {

            $plugins = \core_component::get_plugin_list($type);
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
        $subsystems = \core_component::get_core_subsystems();
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

        return $exclude;
    }

}
