<?php

namespace MoodleAnalyse\Rewrite;

use PHPUnit\Framework\TestCase;

class RelativeDirPathRewriteTest extends TestCase
{

    public function testCalculateRelativePath() {
        $this->assertEquals('target.php', RelativeDirPathRewrite::calculateRelativePath('@/plugin/test.php', '@/plugin/target.php'));
        $this->assertEquals('../target.php', RelativeDirPathRewrite::calculateRelativePath('@/plugin/blah/test.php', '@/plugin/target.php'));
        $this->assertEquals('bar/target.php', RelativeDirPathRewrite::calculateRelativePath('@/plugin/foo/test.php', '@/plugin/foo/bar/target.php'));
        $this->assertEquals('../bar/target.php', RelativeDirPathRewrite::calculateRelativePath('@/plugin/foo/baz/test.php', '@/plugin/foo/bar/target.php'));
        $this->assertEquals('../../bar/target.php', RelativeDirPathRewrite::calculateRelativePath('@/plugin/foo/baz/bar/test.php', '@/plugin/foo/bar/target.php'));
        $this->assertEquals('../../../foo/bar/baz/target.php', RelativeDirPathRewrite::calculateRelativePath('@/plugin/bim/baz/bar/test.php', '@/plugin/foo/bar/baz/target.php'));
    }
}
