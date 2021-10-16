<?php

namespace MoodleAnalyse\Codebase;

use PHPUnit\Framework\TestCase;

class ResolvedIncludeProcessorTest extends TestCase
{

    /**
     * @dataProvider categoriseTestData
     * @param string $resolvedInclude
     * @param string $expectedCategory
     */
    public function testCategorise(string $resolvedInclude, string $expectedCategory)
    {
        $processor = new ResolvedIncludeProcessor();
        $category = $processor->categorise($resolvedInclude);
        $this->assertEquals($expectedCategory, $category);
    }

    public function categoriseTestData()
    {

        yield ['@', 'dirroot'];
        yield ['@/', 'dirroot'];
        yield ['@/config.php', 'config'];
        yield ['@/lib/moodlelib.php', 'simple file'];
        yield ['@/filter/tex/mimetex.linux.aarch64', 'simple file'];
        yield ['@/lib/editor', 'simple dir'];
        yield ['{$fullpath}', 'single var'];
        yield ['@/{$somevariable}', 'full relative path'];
        yield ['@/Some string that happens to contain dirroot @', 'suspect - embedded @'];
        yield ['@/admin/settings/*.php', 'glob'];
        yield ['{$fullblock}/db/install.php', 'fulldir relative'];
        yield ['@/blocks/{$blockname}/version.php', 'simple dynamic file'];
        yield ['@/mod/{$modname}/backup/moodle1/lib.php', 'simple dynamic file'];
        yield ['@/mod/{$data[\'modulename\']}/version.php', 'simple dynamic file'];
        yield ['@/completion/criteria/{$object}.php', 'filename substitution'];
    }
}
