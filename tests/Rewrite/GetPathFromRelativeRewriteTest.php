<?php

namespace MoodleAnalyse\Rewrite;

use MoodleAnalyse\Codebase\PathCode;
use PHPUnit\Framework\TestCase;

class GetPathFromRelativeRewriteTest extends TestCase
{

    /**
     * @dataProvider constructTestData
     */
    public function testConstruct(string $codeString, string $expected, string $pathWithinComponent)
    {
        $pathCode = new PathCode($codeString, 89, 89, 1500, 1500 + strlen($codeString));
        $pathCode->setPathComponent('core_root');
        $pathCode->setPathWithinComponent($pathWithinComponent);
        $pathCode->setResolvedPath('@' . $pathWithinComponent);
        $rewrite = new GetPathFromRelativeRewrite($pathCode);
        $this->assertEquals($expected, $rewrite->getCode());
    }

    /**
     * Get source, expected and path within core.
     * @return \Generator<array{string, string, string}>
     */
    public static function constructTestData(): \Generator
    {
        yield [
            "\$CFG->dirroot . '/' . ltrim(\$observer['includefile'], '/')",
            "\core_component::get_path_from_relative(ltrim(\$observer['includefile'], '/'))",
            "{ltrim(\$observer['includefile'], '/')}"
        ];

        yield [
            '"{$CFG->dirroot}" . autoloader::get_h5p_editor_library_base($languagescript)',
            "\core_component::get_path_from_relative(\core_h5p\local\library\autoloader::get_h5p_editor_library_base(\$languagescript))",
            "@{\core_h5p\local\library\autoloader::get_h5p_editor_library_base(\$languagescript)}"
        ];

        yield [
            '$CFG->dirroot . self::PRESENTATION_FILEPATH',
            "\core_component::get_path_from_relative(self::PRESENTATION_FILEPATH)",
            "{self::PRESENTATION_FILEPATH}"
        ];
    }
}
