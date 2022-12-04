<?php

namespace MoodleAnalyse\Codebase;

use Generator;
use MoodleAnalyse\Visitor\PathFindingVisitor;
use MoodleAnalyse\Visitor\PathResolvingVisitor;
use PhpParser\Lexer;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

class DirrootAnalyserTest extends TestCase
{

    public function testIsDirroot(): void
    {
        $analyser = new DirrootAnalyser();
        foreach (['@', '@/', '@\\', '@{DIRECTORY_SEPARATOR}', '@{\\DIRECTORY_SEPARATOR}'] as $dirroot) {
            $this->assertTrue($analyser->isDirroot($dirroot));
        }
    }

    /**
     * @dataProvider classifyUseData
     */
    public function testClassifyUse(string $code, int $expected): void
    {
        $analyser = new DirrootAnalyser();

        $pathFindingVisitor = new PathFindingVisitor();
        $pathResolvingVisitor = new PathResolvingVisitor();

        $lexer = new Lexer(['usedAttributes' => ['startLine', 'endLine', 'startFilePos', 'endFilePos']]);
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7, $lexer);
        $traverser1 = new NodeTraverser();
        $traverser1->addVisitor(new NameResolver());
        $traverser1->addVisitor(new ParentConnectingVisitor());
        $traverser1->addVisitor($pathFindingVisitor);

        $traverser2 = new NodeTraverser();
        $traverser2->addVisitor(new NameResolver());
        $traverser2->addVisitor(new ParentConnectingVisitor());
        $traverser2->addVisitor($pathResolvingVisitor);

        $code = '<?php ' . $code . ';';
        $nodes = $parser->parse($code);
        $nodes = $traverser1->traverse($nodes);
        $traverser2->traverse($nodes);

        $pathNodes = $pathResolvingVisitor->getPathNodes();
        $this->assertCount(1, $pathNodes);

        $pathNode = $pathNodes[0];

        $result = $analyser->classifyUse($pathNode);
        $this->assertEquals($expected, $result[1]);
    }

    public function classifyUseData(): Generator
    {
        yield [
            'if (strpos($record[\'packagefilepath\'], $CFG->dirroot) !== 0) { }',
            DirrootAnalyser::ABSOLUTE_PATH_CHECK | DirrootAnalyser::NEGATIVE
        ];

        yield [
            'if (substr($something, strlen($CFG->dirroot.\'/\'))) { }',
            DirrootAnalyser::ABSOLUTE_PATH_TO_RELATIVE
        ];

        yield [
            'if (strpos($file, $CFG->dirroot.DIRECTORY_SEPARATOR) === 0) { }',
            DirrootAnalyser::ABSOLUTE_PATH_CHECK
        ];
    }
}
