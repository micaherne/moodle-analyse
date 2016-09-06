<?php

require_once __DIR__ . '/vendor/autoload.php';

$moodleroot = realpath(__DIR__ . '/moodle');

$gen = new \MoodleAnalyse\ControllerGenerator($moodleroot);

// $code = $gen->generate('\\controller\\course\\view', file_get_contents($moodleroot . '/course/view.php'), '/course');
//$code = $gen->generate('\\controller\\enrol\\lti\\cartridge', file_get_contents($moodleroot . '/enrol/lti/cartridge.php'), '/enrol/lti');
//file_put_contents(__DIR__ . '/controller/enrol/lti/cartridge.php', $code);

$whitelistfile = __DIR__ . '/whitelist.json';
if (!file_exists($whitelistfile)) {
    die("Whitelist file required. Run find-entry-points.php to generate it.");
}
$whitelist = (array) json_decode(file_get_contents($whitelistfile));


$errors = [];
foreach (array_keys($whitelist) as $absfile) {
    // This is very Windows-specific
    $base = str_replace($moodleroot, '', $absfile);
    $controllerFile = '\\controller' . $base;
    $controllerClass = substr($controllerFile, 0, -4);
    $pagedir = str_replace('\\', '/', dirname($base));
    echo "Generating $controllerClass $controllerFile\n";
    try {
        $code = $gen->generate($controllerClass, file_get_contents($absfile), $pagedir);
        $controllerdir = dirname(__DIR__ . $controllerFile);
        if (!file_exists($controllerdir)) {
            mkdir($controllerdir, null, true);
        }
        file_put_contents(__DIR__ . $controllerFile, $code);
    } catch (\Exception $e) {
        $errors[] = $absfile;
    }

}

print_r($errors);
