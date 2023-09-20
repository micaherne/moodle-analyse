<?php

namespace MoodleAnalyse\Rewrite;

use MoodleAnalyse\Codebase\CodebasePath;
use MoodleAnalyse\Codebase\PathCode;
use PHPUnit\Framework\TestCase;

class RelativeDirPathRewriteTest extends TestCase
{

    public function testCalculateRelativePath()
    {
        $this->assertEquals(
            'target.php',
            RelativeDirPathRewrite::calculateRelativePath('@/plugin/test.php', '@/plugin/target.php')
        );
        $this->assertEquals(
            '../target.php',
            RelativeDirPathRewrite::calculateRelativePath('@/plugin/blah/test.php', '@/plugin/target.php')
        );
        $this->assertEquals(
            'bar/target.php',
            RelativeDirPathRewrite::calculateRelativePath('@/plugin/foo/test.php', '@/plugin/foo/bar/target.php')
        );
        $this->assertEquals(
            '../bar/target.php',
            RelativeDirPathRewrite::calculateRelativePath('@/plugin/foo/baz/test.php', '@/plugin/foo/bar/target.php')
        );
        $this->assertEquals(
            '../../bar/target.php',
            RelativeDirPathRewrite::calculateRelativePath(
                '@/plugin/foo/baz/bar/test.php',
                '@/plugin/foo/bar/target.php'
            )
        );
        $this->assertEquals(
            '../../../foo/bar/baz/target.php',
            RelativeDirPathRewrite::calculateRelativePath(
                '@/plugin/bim/baz/bar/test.php',
                '@/plugin/foo/bar/baz/target.php'
            )
        );
        $this->assertEquals(
            'gui_ajax/theme/{$theme}/config.php',
            RelativeDirPathRewrite::calculateRelativePath(
                '@/mod/chat/lib.php',
                '@/mod/chat/gui_ajax/theme/{$theme}/config.php'
            )
        );
    }

    /**
     * @param string $componentPath
     * @param string $resolvedPath
     * @dataProvider constructTestData
     */
    public function testConstruct(
        string $codeString,
        string $expected,
        string $pathWithinComponent,
        string $relativeFilePath,
        string $resolvedPath,
        string $componentPath
    ) {
        $pathCode = new PathCode($codeString, 89, 89, 1500, 1500 + strlen($codeString));
        $pathCode->setPathComponent('mod_something');
        $pathCode->setPathWithinComponent($pathWithinComponent);
        $pathCode->setResolvedPath($resolvedPath);
        $codebasePath = new CodebasePath($relativeFilePath, $componentPath, $pathCode);
        $rewrite = new RelativeDirPathRewrite($codebasePath, $componentPath);
        $this->assertEquals($expected, $rewrite->getCode());
    }


    public static function constructTestData()
    {
        yield [
            '$CFG->dirroot.\'/mod/feedback/item/\'.$typ.\'/lib.php\'',
            '__DIR__ . "/item/{$typ}/lib.php"',
            'edit_item.php',
            'mod/feedback/edit_item.php',
            '@/mod/feedback/item/{$typ}/lib.php',
            'mod/feedback'
        ];
    }
}
