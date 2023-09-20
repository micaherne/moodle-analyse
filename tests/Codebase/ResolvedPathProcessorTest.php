<?php

namespace MoodleAnalyse\Codebase;

use PHPUnit\Framework\TestCase;

class ResolvedPathProcessorTest extends TestCase
{

    /**
     * @dataProvider categoriseTestData
     * @param string $resolvedInclude
     * @param string $expectedCategory
     */
    public function testCategoriseResolvedPath(string $resolvedInclude, PathCategory $expectedCategory): void
    {
        $processor = new ResolvedPathProcessor();
        $category = $processor->categoriseResolvedPath($resolvedInclude);
        $this->assertEquals($expectedCategory, $category);
    }

    /**
     * @dataProvider toCodeStringTestData
     * @param string $resolvedInclude
     * @param string $expectedOutput
     */
    public function testToCodeString(string $resolvedInclude, string $expectedOutput): void
    {
        $processor = new ResolvedPathProcessor();
        $output = $processor->toCodeString($resolvedInclude);
        $this->assertEquals($expectedOutput, $output);
    }

    public function testToCodeStringConfig(): void
    {
        $processor = new ResolvedPathProcessor();
        $this->assertEquals('__DIR__ . \'/../../config.php\'',
            $processor->toCodeString('@/config.php', 'lib/test/something.php'));
        $this->assertEquals('__DIR__ . \'/config.php\'',
            $processor->toCodeString('@/config.php', 'index.php'));
        // From restore_includes.php - there's an extra slash.
        $this->assertEquals('$CFG->libdir . \'//questionlib.php\'',
            $processor->toCodeString('@/lib//questionlib.php'));
    }

    /**
     * @dataProvider splitResolvedIncludeData
     */
    public function testSplitResolvedInclude(string $resolvedInclude, array|false $expected): void
    {
        $processor = new ResolvedPathProcessor();
        $method = (new \ReflectionClass($processor))
            ->getMethod('splitResolvedInclude');
        $method->setAccessible(true);
        $this->assertEquals($expected, $method->invoke($processor, $resolvedInclude));
    }

    public static function categoriseTestData(): \Generator
    {

        yield ['@{\DIRECTORY_SEPARATOR}', PathCategory::DirRoot];
        yield ['@', PathCategory::DirRoot];
        yield ['@/', PathCategory::DirRoot];
        yield ['@\\', PathCategory::DirRoot];
        yield ['@/config.php', PathCategory::Config];
        yield ['@/lib/moodlelib.php', PathCategory::SimpleFile];
        yield ['@/filter/tex/mimetex.linux.aarch64', PathCategory::SimpleFile];
        yield ['@/lib/editor', PathCategory::SimpleDir];
        yield ['{$fullpath}', PathCategory::SingleVar];
        yield ['@/{$somevariable}', PathCategory::FullRelativePath];
        yield ['@/Some string that happens to contain dirroot @', PathCategory::Suspect];
        yield ['@/admin/settings/*.php', PathCategory::Glob];
        yield ['{$fullblock}/db/install.php', PathCategory::FullDirRelative];
        yield ['@/blocks/{$blockname}/version.php', PathCategory::SimpleDynamicFile];
        yield ['@/mod/{$modname}/backup/moodle1/lib.php', PathCategory::SimpleDynamicFile];
        yield ['@/mod/{$data[\'modulename\']}/version.php', PathCategory::SimpleDynamicFile];
        yield ['@/completion/criteria/{$object}.php', PathCategory::FilenameSubstitution];
    }

    public static function toCodeStringTestData(): \Generator
    {
        yield ['@\\', '$CFG->dirroot . \'\\\''];
        yield ['@/{ltrim($observer[\'includefile\'], \'/\')}', '$CFG->dirroot . \'/\' . ltrim($observer[\'includefile\'], \'/\')'];
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
        yield ['{$blockname}', '$blockname'];
        yield ['block_{$blockname}.php', '\'block_\' . $blockname . \'.php\''];
        yield ['somedirectory/block_{$blockname}.php', '\'somedirectory/block_\' . $blockname . \'.php\''];
    }

    public static function splitResolvedIncludeData(): \Generator
    {
        yield ['@/lib/moodlelib.php', ['@', 'lib', 'moodlelib.php']];
        yield ['@/{ltrim($observer[\'includefile\'], \'/\')}', ['@', '{ltrim($observer[\'includefile\'], \'/\')}']];
        yield ['@/blocks/{$blockname}/block_{$blockname}.php', ['@', 'blocks', '{$blockname}', 'block_{$blockname}.php']];
    }
}
