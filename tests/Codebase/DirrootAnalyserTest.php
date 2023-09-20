<?php

namespace MoodleAnalyse\Codebase;

use Generator;
use MoodleAnalyse\Visitor\PathFindingVisitor;
use MoodleAnalyse\Visitor\PathResolvingVisitor;
use PhpParser\Lexer;
use PhpParser\Node;
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

    public static function findWrangleNodeData(): Generator
    {
        yield [
            // Not in itself a wrangle node.
            '$x = strlen($CFG->dirroot);',
            function ($wrangleNodes) {
                self::assertCount(1, $wrangleNodes);
                self::assertNull($wrangleNodes[0]);
            }
        ];

        yield [
            '$x = substr($y, strlen($CFG->dirroot));',
            function ($wrangleNodes) {
                self::assertCount(1, $wrangleNodes);
                self::assertNotNull($wrangleNodes[0]);
                $node = $wrangleNodes[0];
                self::assertEquals(
                    DirrootAnalyser::ABSOLUTE_PATH_TO_RELATIVE,
                    $node->getAttribute(DirrootAnalyser::DIRROOT_WRANGLE_CATEGORY)
                );
                self::assertInstanceOf(
                    Node\Expr\Variable::class,
                    $node->getAttribute(DirrootAnalyser::DIRROOT_WRANGLE_VARIABLE_NODE)
                );
                self::assertEquals('y', $node->getAttribute(DirrootAnalyser::DIRROOT_WRANGLE_VARIABLE_NODE)->name);
            }
        ];

        yield [
            '$x = substr($y, strlen($CFG->dirroot) + 1);',
            function ($wrangleNodes) {
                self::assertCount(1, $wrangleNodes);
                self::assertNotNull($wrangleNodes[0]);
                $node = $wrangleNodes[0];
                self::assertEquals(
                    DirrootAnalyser::ABSOLUTE_PATH_TO_RELATIVE | DirrootAnalyser::NO_SLASH,
                    $node->getAttribute(DirrootAnalyser::DIRROOT_WRANGLE_CATEGORY)
                );
                self::assertInstanceOf(
                    Node\Expr\Variable::class,
                    $node->getAttribute(DirrootAnalyser::DIRROOT_WRANGLE_VARIABLE_NODE)
                );
                self::assertEquals('y', $node->getAttribute(DirrootAnalyser::DIRROOT_WRANGLE_VARIABLE_NODE)->name);
            }
        ];


        yield [
            '$x = substr($something, strlen($CFG->dirroot.\'/\'));',
            function ($wrangleNodes) {
                self::assertCount(1, $wrangleNodes);
                self::assertNotNull($wrangleNodes[0]);
                $node = $wrangleNodes[0];
                self::assertEquals(
                    DirrootAnalyser::ABSOLUTE_PATH_TO_RELATIVE | DirrootAnalyser::NO_SLASH,
                    $node->getAttribute(DirrootAnalyser::DIRROOT_WRANGLE_CATEGORY)
                );
                self::assertInstanceOf(
                    Node\Expr\Variable::class,
                    $node->getAttribute(DirrootAnalyser::DIRROOT_WRANGLE_VARIABLE_NODE)
                );
                self::assertEquals(
                    'something',
                    $node->getAttribute(DirrootAnalyser::DIRROOT_WRANGLE_VARIABLE_NODE)->name
                );
            }
        ];

        yield [
            '$x = substr($something, strlen($CFG->dirroot) + 1);',
            function ($wrangleNodes) {
                self::assertCount(1, $wrangleNodes);
                self::assertNotNull($wrangleNodes[0]);
                $node = $wrangleNodes[0];
                self::assertInstanceOf(Node\Expr\FuncCall::class, $node);
                self::assertEquals('substr', $node->name);
                self::assertEquals(
                    DirrootAnalyser::ABSOLUTE_PATH_TO_RELATIVE | DirrootAnalyser::NO_SLASH,
                    $node->getAttribute(DirrootAnalyser::DIRROOT_WRANGLE_CATEGORY)
                );
                self::assertInstanceOf(
                    Node\Expr\Variable::class,
                    $node->getAttribute(DirrootAnalyser::DIRROOT_WRANGLE_VARIABLE_NODE)
                );
                self::assertEquals(
                    'something',
                    $node->getAttribute(DirrootAnalyser::DIRROOT_WRANGLE_VARIABLE_NODE)->name
                );
            }
        ];

        yield [
            'if (strpos($record[\'packagefilepath\'], $CFG->dirroot) !== 0) { }',
            function ($wrangleNodes) {
                self::assertCount(1, $wrangleNodes);
                self::assertNotNull($wrangleNodes[0]);
                self::assertInstanceOf(Node\Expr\BinaryOp::class, $wrangleNodes[0]);
                $node = $wrangleNodes[0];
                self::assertEquals(
                    DirrootAnalyser::ABSOLUTE_PATH_IN_CODEBASE | DirrootAnalyser::NEGATIVE,
                    $node->getAttribute(DirrootAnalyser::DIRROOT_WRANGLE_CATEGORY)
                );
            }
        ];

        yield [
            'if (strpos($file, $CFG->dirroot.DIRECTORY_SEPARATOR) === 0) { }',
            function ($wrangleNodes) {
                self::assertCount(1, $wrangleNodes);
                self::assertNotNull($wrangleNodes[0]);
                $node = $wrangleNodes[0];
                self::assertEquals(
                    DirrootAnalyser::ABSOLUTE_PATH_IN_CODEBASE,
                    $node->getAttribute(DirrootAnalyser::DIRROOT_WRANGLE_CATEGORY)
                );
            }
        ];

        yield [
            '$x = str_replace($CFG->dirroot . \'/\', \'\', $file);',
            function ($wrangleNodes) {
                self::assertCount(1, $wrangleNodes);
                self::assertNotNull($wrangleNodes[0]);
                $node = $wrangleNodes[0];
                self::assertEquals(
                    DirrootAnalyser::ABSOLUTE_PATH_TO_RELATIVE | DirrootAnalyser::NO_SLASH | DirrootAnalyser::ALLOW_RELATIVE_PATHS,
                    $node->getAttribute(DirrootAnalyser::DIRROOT_WRANGLE_CATEGORY)
                );
                self::assertEquals('file', $node->getAttribute(DirrootAnalyser::DIRROOT_WRANGLE_VARIABLE_NODE)->name);
            }
        ];

        yield [
            '$x = str_replace($CFG->dirroot, \'\', $file);',
            function ($wrangleNodes) {
                self::assertCount(1, $wrangleNodes);
                self::assertNotNull($wrangleNodes[0]);
                $node = $wrangleNodes[0];
                self::assertEquals(
                    DirrootAnalyser::ABSOLUTE_PATH_TO_RELATIVE | DirrootAnalyser::ALLOW_RELATIVE_PATHS,
                    $node->getAttribute(DirrootAnalyser::DIRROOT_WRANGLE_CATEGORY)
                );
                self::assertEquals('file', $node->getAttribute(DirrootAnalyser::DIRROOT_WRANGLE_VARIABLE_NODE)->name);
            }
        ];

        yield [
            '$x = strpos($jsfile, $CFG->dirroot . DIRECTORY_SEPARATOR) !== 0;',
            function ($wrangleNodes) {
                self::assertCount(1, $wrangleNodes);
                self::assertNotNull($wrangleNodes[0]);
                $node = $wrangleNodes[0];
                self::assertEquals(
                    DirrootAnalyser::ABSOLUTE_PATH_IN_CODEBASE | DirrootAnalyser::NEGATIVE,
                    $node->getAttribute(DirrootAnalyser::DIRROOT_WRANGLE_CATEGORY)
                );
                self::assertEquals('jsfile', $node->getAttribute(DirrootAnalyser::DIRROOT_WRANGLE_VARIABLE_NODE)->name);
            }
        ];

        yield [
            '$x = str_replace($CFG->dirroot, \'[dirroot]\', $expected)',
            function ($wrangleNodes) {
                self::assertCount(1, $wrangleNodes);
                self::assertNotNull($wrangleNodes[0]);
                $node = $wrangleNodes[0];
                self::assertEquals(
                    DirrootAnalyser::REPLACE_WITH_STRING | DirrootAnalyser::ALLOW_RELATIVE_PATHS,
                    $node->getAttribute(DirrootAnalyser::DIRROOT_WRANGLE_CATEGORY)
                );
                self::assertEquals(
                    'expected',
                    $node->getAttribute(DirrootAnalyser::DIRROOT_WRANGLE_VARIABLE_NODE)->name
                );
                self::assertEquals('[dirroot]', $node->getAttribute(DirrootAnalyser::DIRROOT_WRANGLE_REPLACEMENT_NODE)->value);
            }
        ];

        yield [
            '$x = str_replace("$CFG->dirroot/", \'[dirroot]\', $expected)',
            function ($wrangleNodes) {
                self::assertCount(1, $wrangleNodes);
                self::assertNotNull($wrangleNodes[0]);
                $node = $wrangleNodes[0];
                self::assertEquals(
                    DirrootAnalyser::REPLACE_WITH_STRING | DirrootAnalyser::NO_SLASH | DirrootAnalyser::ALLOW_RELATIVE_PATHS,
                    $node->getAttribute(DirrootAnalyser::DIRROOT_WRANGLE_CATEGORY)
                );
                self::assertEquals(
                    'expected',
                    $node->getAttribute(DirrootAnalyser::DIRROOT_WRANGLE_VARIABLE_NODE)->name
                );
                self::assertEquals('[dirroot]', $node->getAttribute(DirrootAnalyser::DIRROOT_WRANGLE_REPLACEMENT_NODE)->value);
            }
        ];
    }

    /**
     * @dataProvider findWrangleNodeData
     *
     * @param string $code
     * @param callable $test
     * @return void
     */
    public function testFindWrangleNode(string $code, callable $test): void
    {
        $analyser = new DirrootAnalyser();

        $class = new \ReflectionClass(DirrootAnalyser::class);
        $findWrangleNode = $class->getMethod('findWrangleNode');
        $findWrangleNode->setAccessible(true);

        $pathNodes = $this->getPathNodes($code);
        $wrangleNodes = array_map(function ($pathNode) use ($analyser, $findWrangleNode) {
            return $findWrangleNode->invoke($analyser, $pathNode);
        }, $pathNodes);
        $test($wrangleNodes);
    }

    /**
     *
     * @dataProvider pathCodeForWrangleData
     * @param string $code
     * @param array{string, string} $expected
     * @return void
     * @throws \Exception
     */
    public function testPathCodeForWrangle(string $code, array $expected, ?callable $extra = null): void
    {
        $analyser = new DirrootAnalyser();
        $pathNodes = $this->getPathNodes($code);
        if (count($pathNodes) !== 1) {
            self::fail("Expected exactly one path node");
        }

        $pathCodeForWrangle = new \ReflectionMethod(DirrootAnalyser::class, 'pathCodeForWrangle');
        $pathCodeForWrangle->setAccessible(true);

        $findWrangleNode = new \ReflectionMethod(DirrootAnalyser::class, 'findWrangleNode');
        $findWrangleNode->setAccessible(true);

        $wrangleNode = $findWrangleNode->invoke($analyser, $pathNodes[0]);

        $code = '<?php ' . $code . ';';

        $pathCode = $pathCodeForWrangle->invoke($analyser, $wrangleNode, $code);

        self::assertEquals($expected[0], $pathCode->getPathCode());
        self::assertEquals($expected[1], $pathCode->getClassification());

        if (!is_null($extra)) {
            $extra($pathCode);
        }
    }

    public static function pathCodeForWrangleData()
    {
        yield [
            '$x = substr($something, strlen($CFG->dirroot));',
            [
                'substr($something, strlen($CFG->dirroot))',
                DirrootAnalyser::ABSOLUTE_PATH_TO_RELATIVE
            ]

        ];

        yield [
            'if (strpos($record[\'packagefilepath\'], $CFG->dirroot) !== 0) { }',
            [
                'strpos($record[\'packagefilepath\'], $CFG->dirroot) !== 0',
                DirrootAnalyser::ABSOLUTE_PATH_IN_CODEBASE | DirrootAnalyser::NEGATIVE,
                fn(PathCodeDirrootWrangle $pathCode) => self::assertEquals(
                    '$record[\'packagefilepath\']',
                    $pathCode->getVariableName()
                )
            ]
        ];

        yield [
            '$x = str_replace("$CFG->dirroot/", \'[dirroot]\', $expected)',
            [
                'str_replace("$CFG->dirroot/", \'[dirroot]\', $expected)',
                DirrootAnalyser::REPLACE_WITH_STRING | DirrootAnalyser::NO_SLASH | DirrootAnalyser::ALLOW_RELATIVE_PATHS,
            ],
            fn(PathCodeDirrootWrangle $pathCode) => self::assertEquals(
                '\'[dirroot]\'',
                $pathCode->getReplacementString()
            )
        ];

    }

    /**
     * @param string $code
     * @return array<Node>
     */
    private function getPathNodes(string $code): array
    {
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

        return $pathResolvingVisitor->getPathNodes();
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

        $findWrangleNode = new \ReflectionMethod(DirrootAnalyser::class, 'findWrangleNode');
        $findWrangleNode->setAccessible(true);

        $result = $findWrangleNode->invoke($analyser, $pathNode);

        $this->assertEquals($expected, $result->getAttribute(DirrootAnalyser::DIRROOT_WRANGLE_CATEGORY));
    }

    public static function classifyUseData(): Generator
    {
        yield [
            'if (strpos($record[\'packagefilepath\'], $CFG->dirroot) !== 0) { }',
            DirrootAnalyser::ABSOLUTE_PATH_IN_CODEBASE | DirrootAnalyser::NEGATIVE
        ];

        yield [
            '$x = substr($something, strlen($CFG->dirroot));',
            DirrootAnalyser::ABSOLUTE_PATH_TO_RELATIVE
        ];

        yield [
            '$x = substr($something, strlen($CFG->dirroot.\'/\'));',
            DirrootAnalyser::ABSOLUTE_PATH_TO_RELATIVE | DirrootAnalyser::NO_SLASH
        ];

        yield [
            '$x = substr($something, strlen($CFG->dirroot) + 1);',
            DirrootAnalyser::ABSOLUTE_PATH_TO_RELATIVE | DirrootAnalyser::NO_SLASH
        ];

        yield [
            'if (strpos($file, $CFG->dirroot.DIRECTORY_SEPARATOR) === 0) { }',
            DirrootAnalyser::ABSOLUTE_PATH_IN_CODEBASE
        ];
    }

}
