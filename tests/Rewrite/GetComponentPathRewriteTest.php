<?php

namespace MoodleAnalyse\Rewrite;

use MoodleAnalyse\Codebase\PathCode;
use PHPUnit\Framework\TestCase;

class GetComponentPathRewriteTest extends TestCase
{

    private const TEST = 'test3';

    private function t() {
        return 'test2';
    }

    private static function t2() {
        return 'test3';
    }

    // Just a test to understand how string interpolation works in PHP.
    public function testInterpolation() {
        $test = 'hello';
        $test2 = ['hello' => 'test2'];
        $this->assertEquals('hello', "{$test}");
        $func = fn () => 'test';
        $this->assertEquals('test', "{$func()}");
        $this->assertEquals('test2', "{$this->t()}");
        $this->assertEquals('test2', "{$test2['hello']}");

        // Constants and function calls (not method calls) are not interpolated.
        $this->assertNotEquals('test3', "{self::TEST}");
        $this->assertNotEquals('test4', "{str_replace('hello', 'test4', $test)}");
        $this->assertNotEquals('test3', "{self::t2()}");
    }
    /**
     * @return void
     * @dataProvider constructTestData
     */
    public function testConstruct(string $codeString, string $expected, string $component, string $pathWithinComponent)
    {
        $pathCode = new PathCode($codeString, 89, 89, 1500, 1500 + strlen($codeString));
        $pathCode->setPathComponent($component);
        $pathCode->setPathWithinComponent($pathWithinComponent);
        $rewrite = new GetComponentPathRewrite($pathCode);
        $this->assertEquals($expected, $rewrite->getCode());
    }

    /**
     * Get source, expected, component and path within component.
     * @return \Generator<array{string, string, string, string}>
     */
    public static function constructTestData(): \Generator
    {
        // From mod_data: "\$CFG->dirroot . '/mod/' . self::MODULE"
        yield[
            "@/mod/{self::MODULE}/lib.php",
            '\core_component::get_component_path("mod_" . self::MODULE, "lib.php")',
            'mod_{self::MODULE}',
            'lib.php'
        ];

        yield[
            "@/mod/{self::MODULE}/{\$dir1}/{self::get_dir2()}/lib.php",
            '\core_component::get_component_path("mod_" . self::MODULE, "{$dir1}/" . self::get_dir2() . "/lib.php")',
            'mod_{self::MODULE}',
            '{$dir1}/{self::get_dir2()}/lib.php'
        ];

        yield[
            '@/mod/{$someclass::$property}/{$dir1}/{self::get_dir2()}/lib.php',
            '\core_component::get_component_path("mod_{$someclass::$property}", "{$dir1}/" . self::get_dir2() . "/lib.php")',
            'mod_{$someclass::$property}',
            '{$dir1}/{self::get_dir2()}/lib.php'
        ];

        yield[
            '@/mod/{$someclass->method()}/{$dir1}/{self::get_dir2()}/lib.php',
            '\core_component::get_component_path("mod_{$someclass->method()}", "{$dir1}/" . self::get_dir2() . "/lib.php")',
            'mod_{$someclass->method()}',
            '{$dir1}/{self::get_dir2()}/lib.php'
        ];
    }




}
