<?php

namespace MoodleAnalyse\Fake;

class CoreComponent {

    private static $initialised = false;

    /**
     * Set up enough dummy data to get core_component working.
     *
     * @param $moodledir string the root of the Moodle codebase
     */
    public static function init($moodledir) {
        global $CFG, $DB;

        // Only do it once.
        if (self::$initialised) {
            return;
        }

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

        self::$initialised = true;
    }

}
