<?php

namespace MoodleAnalyse\SimpleMvc;

class MoodleConfig {

    private $configFile;

    public function __construct($configFile) {
        $this->configFile = $configFile;
    }

    public function init() {
        // You can get these variables by doing print_r(array_keys($GLOBALS)) immediately
        // after require config.php on a running Moodle server.
        global $CFG, $DB, $PAGE, $USER, $SESSION, $OUTPUT, $SITE, $COURSE, $ME, $FULLME, $FULLSCRIPT, $SCRIPT, $PERF, $ACCESSLIB_PRIVATE;
        require $this->configFile;
    }
}
