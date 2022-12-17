<?php
declare(strict_types=1);

namespace MoodleAnalyse\File;

use Exception;
use Iterator;
use MoodleAnalyse\Codebase\Component\ComponentsFinder;
use MoodleAnalyse\Codebase\ThirdPartyLibsReader;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class FileFinder
{
    private const THIRDPARTYLIBS_XML = 'thirdpartylibs.xml';

    public function __construct(private string $moodleroot)
    {
    }

    /**
     * @param string[] $types file extensions to return, empty means all files
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

        $componentsFinder = new ComponentsFinder();
        $thirdPartyLibsReader = new ThirdPartyLibsReader();

        $components = $componentsFinder->getComponents($this->moodleroot);
        $libDirectories = ['files' => [], 'dirs' => []];
        foreach ($components as $componentDirectory) {
            $thirdPartyLibLocations = $thirdPartyLibsReader->getLocationsRelative($this->moodleroot . '/' . $componentDirectory);
            $libDirectories['files'] = array_merge($libDirectories['files'], $thirdPartyLibLocations['files']);
            $libDirectories['dirs'] = array_merge($libDirectories['dirs'], $thirdPartyLibLocations['dirs']);

        }

        return $libDirectories;
    }


}