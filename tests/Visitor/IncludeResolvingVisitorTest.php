<?php

namespace MoodleAnalyse\Visitor;

use MoodleAnalyse\Visitor\IncludeResolvingVisitor;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

class IncludeResolvingVisitorTest extends TestCase
{
    private NodeTraverser $traverser;
    private Parser $parser;
    private ParentConnectingVisitor $visitor;

    protected function setUp(): void
    {
        $this->parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $this->traverser = new NodeTraverser();
        $this->visitor = new ParentConnectingVisitor();
        $this->traverser->addVisitor($this->visitor);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset($this->parser);
        unset($this->traverser);
        unset($this->visitor);
    }


    /**
     * @dataProvider requireDataProvider
     */
    public function testResolve($path, $require, $expected, $message = '')
    {
        $visitor = new IncludeResolvingVisitor();
        $visitor->setFilePath($path);
        $this->traverser->addVisitor($visitor);
        $nodes = $this->parser->parse("<?php {$require};");
        $this->traverser->traverse($nodes);

        $includes = $visitor->getIncludes();

        $this->assertEquals($expected, $includes[0]->getAttribute(IncludeResolvingVisitor::RESOLVED_INCLUDE), $message);

    }

    public function requireDataProvider(): \Generator
    {
        yield [
            'tag/classes/tag.php',
            'require_once($CFG->dirroot . \'/\' . ltrim($tagarea->callbackfile, \'/\'))',
            '@/{ltrim($tagarea->callbackfile, \'/\')}'
        ];

        yield [
            'lib/tests/some_test.php',
            'require($somevariable);',
            '{$somevariable}',
            'Paths starting with variables should not be changed to relative paths'
        ];

        yield [
            'admin/mnet/trustedhosts.php',
            'include(\'./trustedhosts.html\')',
            '@/admin/mnet/trustedhosts.html',
            'Single dot components should be removed'
        ];

        yield [
            'lib/tests/grading_externallib_test.php',
            'require($CFG->dirroot.\'/grade/grading/form/\'.$rubricdefinition[\'method\'].\'/lib.php\');',
            '@/grade/grading/form/{$rubricdefinition[\'method\']}/lib.php'
        ];

        yield [
            'auth/db/cli/sync_users.php',
            "require_once(__DIR__.'/something/' . functioncall() . '/test.php')",
            '@/auth/db/cli/something/{functioncall()}/test.php'
        ];

        yield [
            'auth/db/cli/sync_users.php',
            "require_once(__DIR__.'/../../../config.php')",
            '@/config.php'
        ];

        yield [
            'mod/chat/renderer.php',
            "require_once(\$CFG->dirroot . '/mod/chat/gui_ajax/theme/'.\$eventmessage->theme.'/config.php')",
            '@/mod/chat/gui_ajax/theme/{$eventmessage->theme}/config.php'
        ];

        yield [
            'mod/assign/overridedelete.php',
            "require_once(dirname(__FILE__) . '/../../config.php')",
            '@/config.php'
        ];

        yield [
            'mod/assign/index.php',
            "require_once(\"../../config.php\")",
            '@/config.php'
        ];

        yield [
            'admin/mnet/peers.php',
            "require_once(\$CFG->dirroot . '/' . \$CFG->admin . '/mnet/tabs.php')",
            '@/admin/mnet/tabs.php'
        ];

        yield [
            'admin/reports.php',
            "require_once(\$CFG->libdir.'/tablelib.php')",
            '@/lib/tablelib.php'
        ];
        yield [
            "admin/mnet/peers.php",
            "require(__DIR__.'/../../config.php')",
            '@/config.php'
        ];
        yield [
            'admin/tool/analytics/classes/external.php',
            "require_once(\"\$CFG->libdir/externallib.php\")",
            '@/lib/externallib.php'
        ];
        yield [
            "admin/tool/dataprivacy/classes/task/expired_retention_period.php",
            "require_once(\$CFG->dirroot . '/' . \$CFG->admin . '/tool/dataprivacy/lib.php')",
            '@/admin/tool/dataprivacy/lib.php'
        ];
        yield [
            'mod/assign/classes/task/cron_task.php',
            "require_once(\$CFG->dirroot . '/mod/assign/submission/' . \$name . '/locallib.php')",
            '@/mod/assign/submission/{$name}/locallib.php'
        ];

        $in = fopen(__DIR__ . '/../fixtures/requires.csv', 'r');
        while($row = fgetcsv($in)) {
            yield [$row[0], 'require_once' . $row[1], $row[2]];
        }
        fclose($in);
    }


}
