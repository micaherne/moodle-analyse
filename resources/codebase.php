<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

class core_codebase
{

    protected static $supportsubplugins = ['mod', 'editor', 'tool', 'local'];

    protected static $componentpaths = null;

    public static function path($relativepath)
    {
        global $CFG;
        return $CFG->dirroot . $relativepath;
    }

    public static function component_path(string $type, ?string $name, string $path)
    {
        global $CFG;

        // We have made core_root up, so deal with this before real ones.
        if ($type === 'core' && $name === 'root') {
            return self::path_from_root($CFG->dirroot, $path);
        }

        // Deal with libdir too.
        $libdir = realpath(__DIR__ . '/..');

        if ($type === 'core' && is_null($name)) {
            return self::path_from_root($libdir, $path);
        }

        // Use core_component if it exists (without loading it if it isn't already)
        if (class_exists('core_component', false)) {
            if (is_null($name)) {
                $component = $type;
            } else {
                $component = $type . '_' . $name;
            }
            $typedir = core_component::get_component_directory($component);
            if ($typedir) {
                return self::path_from_root($typedir, $path);
            } else {
                // If it's a non-existent plugin, just return where it would be.
                if ($type !== 'core') {
                    $plugintypes = core_component::get_plugin_types();
                    if (array_key_exists($type, $plugintypes)) {
                        return self::path_from_root($plugintypes[$type] . '/' . $name, $path);
                    }
                }

                throw new coding_exception("Directory for $component not found");
            }
        }

        $componentpaths = self::get_component_paths();

        if ($type === 'core') {
            // If name is null libdir will have been returned earlier.
            return self::path_from_root($componentpaths->subsystems->$name, $path);
        } else {
            return self::path_from_root(dirname($libdir) . '/' . $componentpaths->plugintypes->$type . '/' . $name,
                $path );
        }
    }

    /**
     * @param string $root
     * @param string $relativepath
     * @return string
     */
    private static function path_from_root(string $root, string $relativepath): string
    {
        // No closing slash to be added if it's empty.
        if ($relativepath === '') {
            return $root;
        }

        // We don't want to ltrim the slashes if it's only a slash.
        if ($relativepath === '/') {
            return $root . '/';
        }

        return $root . '/' . ltrim($relativepath, '/');
    }

    private static function get_component_paths(): object
    {
        if (isset(self::$componentpaths)) {
            return self::$componentpaths;
        }

        $moodleroot = __DIR__ . '/../..';
        $components = json_decode(file_get_contents(__DIR__ . '/../components.json'));
        foreach (self::$supportsubplugins as $plugintype) {
            $plugintyperoot = $moodleroot . '/' . $components->plugintypes->$plugintype;
            foreach (glob($plugintyperoot . '/*/db/subplugins.json') as $subpluginsfile) {
                $relativepath = substr($subpluginsfile, strlen($plugintyperoot) + 1);
                $plugin = $plugintype . '_' . substr($relativepath, 0, strpos($relativepath, '/'));
                $data = json_decode(file_get_contents($subpluginsfile));
                if (!$data) {
                    continue;
                }
                foreach ($data->plugintypes as $plugintype => $relativepath) {
                    $components->plugintypes->$plugintype = $relativepath;
                }
            }
        }

        self::$componentpaths = $components;
        return $components;
    }
}
