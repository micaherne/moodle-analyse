<?php
declare(strict_types=1);

namespace MoodleAnalyse\File;

use Exception;
use Iterator;
use MoodleAnalyse\Codebase\Component\ComponentsFinder;
use MoodleAnalyse\Codebase\ThirdPartyLibsReader;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * A class to find files in a Moodle codebase, taking account of third party libraries if necessary.
 */
class FileFinder
{

    public function __construct(protected string $moodleroot)
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
     * @return array{files: array<string>, dirs: array<string>}
     * @throws Exception
     */
    protected function getThirdPartyLibLocations(): array
    {

        $componentsFinder = new ComponentsFinder();
        $thirdPartyLibsReader = new ThirdPartyLibsReader();

        $components = $componentsFinder->getComponents($this->moodleroot);
        $libDirectories = ['files' => [], 'dirs' => []];
        foreach ($components as $componentDirectory) {
            $thirdPartyLibLocationsLocal = $thirdPartyLibsReader->getLocationsRelative($this->moodleroot . '/' . $componentDirectory);
            $thirdPartyLibLocations = $this->makeRelative($thirdPartyLibLocationsLocal, $componentDirectory);
            $libDirectories['files'] = array_merge($libDirectories['files'], $thirdPartyLibLocations['files']);
            $libDirectories['dirs'] = array_merge($libDirectories['dirs'], $thirdPartyLibLocations['dirs']);

        }

        return $libDirectories;
    }

    /**
     * @param array{files: array<string>, dirs: array<string>} $libLocations
     * @param string $parent the relative parent of the directory the third party libs file was in
     * @return array
     */
    private function makeRelative(array $libLocations, string $parent): array {
        return [
            'files' => array_map(fn($file) => $parent . '/' . $file, $libLocations['files']),
            'dirs' => array_map(fn($dir) => $parent . '/' . $dir, $libLocations['dirs'])
        ];
    }


}