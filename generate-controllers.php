<?php

require_once __DIR__ . '/vendor/autoload.php';

$moodleroot = realpath(__DIR__ . '/moodle');

$gen = new \MoodleAnalyse\ControllerGenerator($moodleroot);
$entryPoints = new \MoodleAnalyse\EntryPoint\EntryPointIterator($moodleroot, __DIR__ . '/whitelist.json');

$errors = [];

$aborts = [];

foreach ($entryPoints as $absfile) {

    // This is very Windows-specific
    $base = str_replace($moodleroot, '', $absfile);

    // TODO: Exclude these files somewhere else.
    if (in_array($base, ['\\admin\\index.php', '\\install.php'])) {
        continue;
    }

    $controllerFile = '\\controller' . $base;
    $controllerClass = substr($controllerFile, 0, -4);
    $pagedir = str_replace('\\', '/', dirname($base));
    echo "Generating $controllerClass $controllerFile\n";

    try {
        $contents = file_get_contents($absfile);

        // TODO: What do we do about ABORT_AFTER_CONFIG scripts?
        if (strpos($contents, 'ABORT_AFTER_CONFIG') !== false) {
            $aborts[] = $base;
            continue;
        }

        $code = $gen->generate($controllerClass, $contents, $pagedir);
        $controllerdir = dirname(__DIR__ . $controllerFile);
        if (!file_exists($controllerdir)) {
            mkdir($controllerdir, null, true);
        }
        file_put_contents(__DIR__ . $controllerFile, $code);
    } catch (\Exception $e) {
        $errors[$absfile] = $e->getMessage();
    }

}

print_r($errors);
print_r($aborts);
