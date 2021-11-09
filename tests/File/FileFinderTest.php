<?php

namespace MoodleAnalyse\File;

use PHPUnit\Framework\TestCase;
use SebastianBergmann\FileIterator\Iterator;
use Symfony\Component\Finder\SplFileInfo;

class FileFinderTest extends TestCase
{

    /**
     * @throws \Exception
     */
    public function testGetFileIterator()
    {
        $this->markTestSkipped("This is very slow");

        $moodleroot = __DIR__ . '/../../moodle';
        if (!is_dir($moodleroot)) {
            $this->markTestSkipped("No moodle codebase found");
        }
        $fileFinder = new FileFinder($moodleroot);

        $iterator = new class($fileFinder->getFileIterator()) extends \IteratorIterator {
            public function current()
            {
                return $this->getInnerIterator()->current()->getRelativePathname();
            }

        };

        $this->assertContains('admin' . DIRECTORY_SEPARATOR . 'antiviruses.php', $iterator);
        $this->assertContains('analytics' . DIRECTORY_SEPARATOR . 'lib.php', $iterator);
        // This test takes about 12 seconds.
        $this->assertNotContains('lib' . DIRECTORY_SEPARATOR . 'pear' . DIRECTORY_SEPARATOR . 'HTML' . DIRECTORY_SEPARATOR . 'QuickForm.php', $iterator);

    }
}
