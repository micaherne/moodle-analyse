<?php

namespace MoodleAnalyse;

use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

class CodebasePathTest extends TestCase
{

    private CodebasePath $codebasePath;
    private $parser;

    protected function setUp(): void
    {
        $this->parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $this->codebasePath = new CodebasePath($this->parser);
    }


    public function testNormalisedPathToNode()
    {
        $a = $this->codebasePath->normalisedPathToNode('@/user/lib.php');
    }
}
