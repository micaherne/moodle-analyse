<?php
declare(strict_types=1);

namespace MoodleAnalyse\File;

use Exception;
use Iterator;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class FileFinder
{
    const THIRDPARTYLIBS_XML = 'thirdpartylibs.xml';
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
        $finder->in($this->moodleroot);
        if (!$includeThirdPartyLibs) {
            $finder->exclude($this->getThirdPartyLibDirectories());
        }
        return $finder->name(array_map(fn($type) => '*.' . $type, $types))->files()->getIterator();
    }

    /**
     * @return string[]
     */
    private function getThirdPartyLibDirectories(): array
    {
        $libFileDirectories = ['lib'];
        $components = json_decode(file_get_contents($this->moodleroot . '/lib/components.json'));

        // Find directories that may contain thirdpartylibs.xml files.
        $libFileDirectories = array_merge($libFileDirectories, array_filter(array_values((array) $components->subsystems)));
        foreach ((array) $components->plugintypes as $plugintypeRoot) {
            $dirs = scandir($this->moodleroot . '/' . $plugintypeRoot);
            foreach ($dirs as $dir) {
                if (str_starts_with($dir, '.')) {
                    continue;
                }
                $libFileDirectories[] = $plugintypeRoot . '/' . $dir;
            }
        }
        $libFileDirectories = array_merge($libFileDirectories, array_values((array) $components->plugintypes));

        $libDirectories = [];
        foreach ($libFileDirectories as $libFileDirectory) {
            $thirdPartyLibFile = $this->moodleroot . '/' . $libFileDirectory . '/' . self::THIRDPARTYLIBS_XML;
            if (!file_exists($thirdPartyLibFile)) {
                continue;
            }
            $xml = simplexml_load_file($thirdPartyLibFile);

            // It may be a single SimpleXMLElement or an array of them.
            foreach ($xml->library as $library) {
                $libDirectories[] = $libFileDirectory . '/' . $library->location;
            }

        }
        return $libDirectories;
    }


}