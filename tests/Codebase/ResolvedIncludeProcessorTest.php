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

    /**
     * @dataProvider toCodeStringTestData
     * @param string $resolvedInclude
     * @param string $expectedOutput
     */
    public function testToCodeString(string $resolvedInclude, string $expectedOutput) {
        $processor = new ResolvedIncludeProcessor();
        $output = $processor->toCodeString($resolvedInclude);
        $this->assertEquals($expectedOutput, $output);
    }

    public function testToCodeStringConfig() {
        $processor = new ResolvedIncludeProcessor();
        $this->assertEquals('__DIR__ . \'/../../config.php\'',
            $processor->toCodeString('@/config.php', 'lib/test/something.php'));
        $this->assertEquals('__DIR__ . \'/config.php\'',
            $processor->toCodeString('@/config.php', 'index.php'));
    }

    public function categoriseTestData()
    {

        yield ['@', 'dirroot'];
        yield ['@/', 'dirroot'];
        yield ['@\\', 'dirroot'];
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

    public function toCodeStringTestData()
    {
        yield ['', '\'\''];
        yield ['@{$themetestdir}{self::get_behat_tests_path()}', '$CFG->dirroot . $themetestdir . self::get_behat_tests_path()'];
        yield ['@/{\BEHAT_PARALLEL_SITE_NAME}{$i}', '$CFG->dirroot . \'/\' . \BEHAT_PARALLEL_SITE_NAME . $i'];
        yield ['{$path}/db/mobile.php', '$path . \'/db/mobile.php\''];
        yield ['{$this->full_path(\'settings.php\')}', '$this->full_path(\'settings.php\')'];
        yield ['@/install/lang/{$options[\'lang\']}', '$CFG->dirroot . \'/install/lang/\' . $options[\'lang\']'];
        yield ['@/blocks/{$blockname}/block_{$blockname}.php', '$CFG->dirroot . \'/blocks/\' . $blockname . \'/block_\' . $blockname . \'.php\''];
        yield ['@', '$CFG->dirroot'];
        yield ['@/mod/assign/lib.php', '$CFG->dirroot . \'/mod/assign/lib.php\''];
        yield ['@/lib', '$CFG->libdir'];
        yield ['@/admin', '$CFG->dirroot . \'/\' . $CFG->admin'];
    }
}
