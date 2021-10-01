<?php

/*
 * This script takes a previous list of require test cases and converts them for the current implementation.
 */
$in = fopen(__DIR__ . '/../tests/fixtures/requires-original.csv', 'r');
$out = fopen(__DIR__ . '/../tests/fixtures/requires.csv', 'w');
$components = json_decode(file_get_contents(__DIR__ . '/../moodle/lib/components.json'));
while ($row = fgetcsv($in)) {
    [$type, $name] = explode('_', $row[0]);
    if (property_exists($components->plugintypes, $type)) {
        $row[0] = str_replace('\\', '/', $components->plugintypes->$type . '/' . $name . '/' . $row[3]);
        fputcsv($out, $row);
    } else {
        echo "Plugin type $type not found \n";
    }
}

fclose($out);
fclose($in);