<?php

$pattern = '/require.*\.php/';

$files = array_keys((array) json_decode(file_get_contents('whitelist.json')));
echo count($files) . "\n";
exit;
$requireconfigs = [];
$requirenocfgs = [];
$nocfgcount = 0;
foreach ($files as $file) {
    $requires = preg_grep($pattern, file($file));
    foreach ($requires as $line => $code) {
        if (strpos($code, 'config.php') !== false) {
            if (!isset($requireconfigs[$file])) {
                $requireconfigs[$file] = [];
            }
            $requireconfigs[$file][$line] = $code;
        } else {
            if (strpos($code, '$CFG') === false) {
                if (!isset($requirenocfgs[$file])) {
                    $requirenocfgs[$file] = [];
                }
                $requirenocfgs[$file][$line] = $code;
                $nocfgcount++;
            }
        }
    }
}

$codelines = [];
foreach ($requireconfigs as $file  => $code) {
    if (count($code) != 1) {
        echo "Problem with $file\n";
        continue;
    }

    $configcode = trim(array_values($code)[0]);

    if (!isset($codelines[$configcode])) {
        $codelines[$configcode] = 0;
    }
    $codelines[$configcode] += 1;
}

print_r($requirenocfgs);
$cnt = count($requirenocfgs);
echo "$nocfgcount dodgy requires found in {$cnt} files\n";
