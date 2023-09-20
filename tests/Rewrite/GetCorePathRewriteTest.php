<?php

namespace MoodleAnalyse\Rewrite;

use MoodleAnalyse\Codebase\PathCode;
use PHPUnit\Framework\TestCase;

class GetCorePathRewriteTest extends TestCase
{

    /**
     * @dataProvider constructTestData
     */
    public function testConstruct(string $codeString, string $expected, string $pathWithinComponent)
    {
        $pathCode = new PathCode($codeString, 89, 89, 1500, 1500 + strlen($codeString));
        $pathCode->setPathComponent('core_root');
        $pathCode->setPathWithinComponent($pathWithinComponent);
        $rewrite = new GetCorePathRewrite($pathCode);
        $this->assertEquals($expected, $rewrite->getCode());
    }

    /**
     * Get source, expected and path within core.
     * @return \Generator<array{string, string, string}>
     */
    public static function constructTestData(): \Generator
    {
        yield [
            "@/lib/javascript.php",
            '\core_component::get_core_path("lib/javascript.php")',
            'lib/javascript.php'
        ];

        yield [
            '@/lib/{$file}.php',
            '\core_component::get_core_path("lib/{$file}.php")',
            'lib/{$file}.php'
        ];

        yield [
            '@/lib/{self::FILE}.php',
            '\core_component::get_core_path("lib/" . self::FILE . ".php")',
            'lib/{self::FILE}.php'
        ];
    }
}
