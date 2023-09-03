<?php

namespace MoodleAnalyse\Codebase\Component;

class ComponentsFinder
{

    // Taken from core_component.
    // TODO: We should use the parser to get this.
    private static $ignoreddirs = [
        'CVS' => true,
        '_vti_cnf' => true,
        'amd' => true,
        'classes' => true,
        'db' => true,
        'fonts' => true,
        'lang' => true,
        'pix' => true,
        'simpletest' => true,
        'templates' => true,
        'tests' => true,
        'yui' => true,
    ];

    // Also from core_component.
    protected static $supportsubplugins = ['mod', 'editor', 'tool', 'local'];

    public function getComponents($moodleDirectory): \Generator {
        $mainComponentsFile = $moodleDirectory . '/lib/components.json';
        if (!is_file($mainComponentsFile)) {
            throw new \Exception("Components file not found in $moodleDirectory/lib/components.json");
        }

        yield 'core' => 'lib';
        $components = json_decode(file_get_contents($mainComponentsFile));
        foreach ($components->subsystems as $subsystem => $subsystemDirectory) {
            if (!is_null($subsystemDirectory)) {
                yield 'core_' . $subsystem => $subsystemDirectory;
            }
        }

        $pathsWithSubPlugins = [];
        foreach ($components->plugintypes as $plugintype => $plugintypeDirectory) {
            $pluginTypeDirAbsolute = $moodleDirectory . '/' . $plugintypeDirectory;
            foreach (new \DirectoryIterator($pluginTypeDirAbsolute) as $dir) {
                if ($dir->isDot()) {
                    continue;
                }
                if (!$dir->isDir()) {
                    continue;
                }
                $dirname = $dir->getFilename();
                if (array_key_exists($dirname, self::$ignoreddirs) && !($plugintype === 'auth' && $dirname === 'db')) {
                    continue;
                }
                $pluginDirectory = $plugintypeDirectory . '/' . $dirname;
                yield $plugintype . '_' . $dirname => $pluginDirectory;
                $subpluginsJsonPath = $pluginTypeDirAbsolute . '/' . $dirname . '/db/subplugins.json';
                if (in_array($plugintype, self::$supportsubplugins) && is_file($subpluginsJsonPath)) {
                    $pathsWithSubPlugins[] = $pluginDirectory;
                }
            }
        }

        foreach ($pathsWithSubPlugins as $pathWithSubPlugins) {
            foreach ($this->getSubplugins($pathWithSubPlugins, $moodleDirectory) as $subplugin => $subpluginDir) {
                yield $subplugin => $subpluginDir;
            }
        }
    }

    /**
     * @param mixed $pathWithSubPlugins
     * @param $moodleDirectory
     */
    public function getSubplugins(string $pathWithSubPlugins, string $moodleDirectory): \Generator
    {
        $subpluginsJsonLocation = $moodleDirectory . '/' . $pathWithSubPlugins . '/db/subplugins.json';
        if (!is_file($subpluginsJsonLocation)) {
            return;
        }
        $components = json_decode(file_get_contents($subpluginsJsonLocation));
        foreach ($components->plugintypes as $plugintype => $plugintypeDirectory) {
            $pluginTypeDirAbsolute = $moodleDirectory . '/' . $plugintypeDirectory;
            foreach (new \DirectoryIterator($pluginTypeDirAbsolute) as $dir) {
                if ($dir->isDot()) {
                    continue;
                }
                if (!$dir->isDir()) {
                    continue;
                }
                $dirname = $dir->getFilename();
                if (array_key_exists($dirname, self::$ignoreddirs) && !($plugintype === 'auth' && $dirname === 'db')) {
                    continue;
                }
                yield $plugintype . '_' . $dirname => $plugintypeDirectory . '/' . $dirname;
            }
        }
    }

}