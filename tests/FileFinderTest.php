<?php

namespace MoodleAnalyse\File;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;

class FileFinderTest extends TestCase
{

    /**
     * @throws \Exception
     */
    public function testGetFileIterator()
    {
        $moodleroot = __DIR__ . '/../moodle';
        if (!is_dir($moodleroot)) {
            $this->markTestSkipped("No moodle codebase found");
        }
        $fileFinder = new FileFinder($moodleroot);
        /** @var SplFileInfo $file */
        foreach ($fileFinder->getFileIterator() as $file) {
            $contents = $file->getContents();
            if (str_contains($contents, 'config.php')) {
                echo $file->getRelativePathname() . "\n";
            }
        }
    }
}
