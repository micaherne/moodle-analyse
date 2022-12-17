<?php
// This is not a real class - it's just an aide memoire for the changes that need to be made
// to core_component to make it work with the rewritten code.

class core_component
{

    protected static $componentloader = null;

    protected static function get_component_loader() {
        if (is_null(self::$componentloader)) {
            if (function_exists('moodle_get_component_loader')) {
                self::$componentloader = moodle_get_component_loader();
            } else {
                self::$componentloader = false;
            }
        }
        if (self::$componentloader === false) {
            return null;
        }
        return self::$componentloader;
    }

    protected static function fetch_plugins() {

        if ($componentloader = self::get_component_loader()) {
            $extraplugins = $componentloader->fetch_plugins($plugintype);
            // Overwrite built-in plugins with externally loaded ones.
            foreach ($extraplugins as $name => $dir) {
                $result[$name] = $dir;
            }
        }

    }

    /**
     * Get the file path within a component directory.
     *
     * @param $component
     * @param $relativepath
     * @return string|null
     */
    public static function get_component_path($component, $relativepath) {
        global $CFG;

        if (is_null($component)) {
            return $CFG->dirroot . '/' . \ltrim($relativepath, '/');
        }

        if (is_null(self::$plugintypes)) {
            // Assume it's a core path.
            // TODO: Can we make this assumption? If not we may have to ask the component loader, which would be slow.
            return $CFG->dirroot . '/' . \ltrim($relativepath, '/');
        }

        $dir = self::get_component_directory($component);
        if (is_null($dir)) {
            if (strpos($component, '_') === false) {
                return null;
            }
            list($type, $name) = self::normalize_component($component);
            $typedir = self::$plugintypes[$type] ?? null;
            if (is_null($typedir)) {
                return null;
            }
            $dir = $typedir . '/' . $name;
        }
        if (strlen($relativepath) === 0) {
            return $dir;
        }
        return $dir . '/' . ltrim($relativepath, '/');
    }

    /**
     * Get the path to a file / directory given the standard Moodle path from wwwroot.
     *
     * @param $relativepath
     * @return string
     */
    public static function get_path_from_relative($relativepath) {
        global $CFG;

        if ($relativepath === '') {
            return $CFG->dirroot;
        }

        if ($loader = self::get_component_loader()) {
            if (!is_null($pathfromloader = $loader->get_path_from_relative($relativepath))) {
                return $pathfromloader;
            }
        }

        return $CFG->dirroot . '/' . ltrim($relativepath, '/\\');
    }

}
