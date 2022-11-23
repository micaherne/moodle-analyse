<?php
declare(strict_types=1);

namespace MoodleAnalyse\File;

use Exception;
use Iterator;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class FileFinder
{
    private const THIRDPARTYLIBS_XML = 'thirdpartylibs.xml';
    private string $moodleroot;

    /**
     * @param string $moodleroot
     */
    public function __construct(string $moodleroot)
    {
        $this->moodleroot = $moodleroot;
    }

    /**
     * @param string[] $types file extensions to return, empty means all files
     * @param bool $includeThirdPartyLibs
     * @return Iterator<SplFileInfo>
     * @throws Exception
     */
    public function getFileIterator(array $types = ['php'], bool $includeThirdPartyLibs = false): Iterator
    {
        $finder = new Finder();
        $finder->exclude(['.git']);
        $finder->in($this->moodleroot);
        if (!$includeThirdPartyLibs) {
            $finder->exclude(['vendor', 'node_modules']);
            $thirdPartyLibLocations = $this->getThirdPartyLibLocations();
            $finder->exclude($thirdPartyLibLocations['dirs']);
            $finder->notPath($thirdPartyLibLocations['files']);
        }
        return $finder->name(array_map(fn($type) => '*.' . $type, $types))->files()->getIterator();
    }

    /**
     * @return array<array<int, string>>
     * @throws Exception
     */
    private function getThirdPartyLibLocations(): array
    {
        $libFileDirectories = ['lib'];
        $componentsJsonContents = file_get_contents($this->moodleroot . '/lib/components.json');
        if ($componentsJsonContents === false) {
            throw new Exception("Unable to read lib/components.json");
        }
        $components = json_decode($componentsJsonContents);

        // Find directories that may contain thirdpartylibs.xml files.
        $libFileDirectories = array_merge($libFileDirectories, array_filter(array_values((array) $components->subsystems)));
        foreach ((array) $components->plugintypes as $plugintypeRoot) {
            $dirs = scandir($this->moodleroot . '/' . $plugintypeRoot);
            if ($dirs === false) {
                throw new Exception("Unable to open directory $plugintypeRoot");
            }
            foreach ($dirs as $dir) {
                if (str_starts_with($dir, '.')) {
                    continue;
                }
                $libFileDirectories[] = $plugintypeRoot . '/' . $dir;
            }
        }
        $libFileDirectories = array_merge($libFileDirectories, array_values((array) $components->plugintypes));

        $libDirectories = ['files' => [], 'dirs' => []];
        foreach ($libFileDirectories as $libFileDirectory) {
            $thirdPartyLibFile = $this->moodleroot . '/' . $libFileDirectory . '/' . self::THIRDPARTYLIBS_XML;
            if (!file_exists($thirdPartyLibFile)) {
                continue;
            }
            $xml = simplexml_load_file($thirdPartyLibFile);
            if ($xml === false) {
                throw new Exception("Unable to read $thirdPartyLibFile as XML");
            }

            // It may be a single SimpleXMLElement or an array of them.
            foreach ($xml->library as $library) {
                $location = $libFileDirectory . '/' . $library->location;
                if (is_dir($this->moodleroot . '/' . $location)) {
                    $libDirectories['dirs'][] = $location;
                } else {
                    $libDirectories['files'][] = $location;
                }

            }

        }

        return $libDirectories;
    }


}