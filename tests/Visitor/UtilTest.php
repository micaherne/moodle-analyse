<?php

namespace MoodleAnalyse\Visitor;

use PhpParser\Parser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

class UtilTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
    }


    public function testIsMoodleInternalCheck()
    {
        $codes = [
            '<?php defined("MOODLE_INTERNAL") || die;',
            '<?php defined("MOODLE_INTERNAL") or die;'
        ];

        foreach ($codes as $code) {
            $nodes = $this->parser->parse($code);
            $this->assertTrue(Util::isMoodleInternalCheck($nodes[0]->expr));
        }
    }

    public function testIsCliScriptDefine()
    {
        $codes = [
            '<?php define("CLI_SCRIPT", 1);',
            '<?php define("CLI_SCRIPT", true);'
        ];

        foreach ($codes as $code) {
            $nodes = $this->parser->parse($code);
            $this->assertTrue(Util::isCliScriptDefine($nodes[0]));
        }
    }
}
