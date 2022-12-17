<?php

namespace MoodleAnalyse\Visitor;

use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

class CliScriptFindingVisitorTest extends TestCase
{
    private \PhpParser\Parser $parser;
    private NodeTraverser $traverser;
    private NodeVisitor $visitor;

    protected function setUp(): void
    {
        $this->parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $this->traverser = new NodeTraverser();
        $this->visitor = new CliScriptFindingVisitor();
        $this->traverser->addVisitor($this->visitor);
    }

    public function testFindCliScript()
    {
        $nodes = $this->parser->parse('<?php define(\'CLI_SCRIPT\', 1);');
        $nodes = $this->traverser->traverse($nodes);
        $this->assertTrue($this->visitor->isCliScript());

        $nodes = $this->parser->parse('<?php define(\'CLI_SCRIPT\', true);');
        $nodes = $this->traverser->traverse($nodes);
        $this->assertTrue($this->visitor->isCliScript());

        $nodes = $this->parser->parse('<?php define(\'CLI_SCRIPT\', false);');
        $nodes = $this->traverser->traverse($nodes);
        $this->assertFalse($this->visitor->isCliScript());

        $nodes = $this->parser->parse('<?php define(\'CLI_SCRIPT\', 0);');
        $nodes = $this->traverser->traverse($nodes);
        $this->assertFalse($this->visitor->isCliScript());
    }


    protected function tearDown(): void
    {
        parent::tearDown();
        unset($this->parser);
        unset($this->traverser);
        gc_collect_cycles();
    }
}
