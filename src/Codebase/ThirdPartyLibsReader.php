<?php

namespace MoodleAnalyse\Codebase;

use Exception;

class ThirdPartyLibsReader
{

    private const THIRDPARTYLIBS_XML = 'thirdpartylibs.xml';

    /**
     * @param string $componentDirectory full path to the component directory
     * @return array{ files: array<string>, dirs: array<string> }
     */
    public function getLocationsAbsolute(string $componentDirectory): array {
        $result = [];
        foreach ($this->getLocationsRelative($componentDirectory) as $type => $locations) {
            $result[$type] = array_map(fn($location) => $componentDirectory . '/' . $location, $locations);
        }
        return $result;
    }

    /**
     * @param string $componentDirectory full path to the component directory
     * @return array{ files: array<string>, dirs: array<string> }
     * @throws Exception
     */
    public function getLocationsRelative(string $componentDirectory): array {
        $result = ['files' => [], 'dirs' => []];
        $thirdPartyLibFile = $componentDirectory . '/' . self::THIRDPARTYLIBS_XML;

        if (!file_exists($thirdPartyLibFile)) {
            return $result;
        }

        $xml = simplexml_load_file($thirdPartyLibFile);
        if ($xml === false) {
            throw new Exception("Unable to read $thirdPartyLibFile as XML");
        }

        // It may be a single SimpleXMLElement or an array of them.
        foreach ($xml->library as $library) {
            $location = $library->location;
            if (is_dir($componentDirectory . '/' . $location)) {
                $result['dirs'][] = (string) $location;
            } else {
                $result['files'][] = (string) $location;
            }

        }
        return $result;
    }

}