<?php

namespace MoodleAnalyse\File;

use MoodleAnalyse\Visitor\IncludeResolvingVisitor;
use MoodleAnalyse\Visitor\PathFindingVisitor;
use MoodleAnalyse\Visitor\PathResolvingVisitor;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

class PathResolvingVisitorTest extends TestCase
{
    private NodeTraverser $traverser;
    private Parser $parser;
    private ParentConnectingVisitor $parentConnectingVisitor;
    private NodeTraverser $preProcessor;

    protected function setUp(): void
    {
        $this->parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $this->traverser = new NodeTraverser();
        $this->preProcessor = new NodeTraverser();
        $this->parentConnectingVisitor = new ParentConnectingVisitor();
        $this->traverser->addVisitor($this->parentConnectingVisitor);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset($this->parser);
        unset($this->traverser);
        unset($this->parentConnectingVisitor);
    }


    /**
     * @dataProvider requireDataProvider
     */
    public function testResolve($path, $require, $expected, $message = '')
    {

        $visitor = new PathResolvingVisitor();
        $visitor->setFilePath($path);

        $this->traverser->addVisitor($visitor);
        $nodes = $this->parser->parse("<?php {$require};");

        $this->preProcessor->addVisitor($this->parentConnectingVisitor);
        $this->preProcessor->addVisitor(new PathFindingVisitor());
        $nodes = $this->preProcessor->traverse($nodes);

        $this->traverser->traverse($nodes);

        $pathNodes = $visitor->getPathNodes();

        $this->assertEquals($expected, $pathNodes[0]->getAttribute(IncludeResolvingVisitor::RESOLVED_INCLUDE), $message);

    }

    public function requireDataProvider(): \Generator
    {
        yield [
            'mod/assign/feedback/editpdf/classes/pdf.php',
            '$CFG->dirroot . self::BLANK_PDF',
            '@{self::BLANK_PDF}'
        ];

        yield [
            'h5p/classes/local/library/handler.php',
            'file_exists($CFG->dirroot . static::get_h5p_core_library_base($classes[$classname]))',
            '@{static::get_h5p_core_library_base($classes[$classname])}'
        ];

        yield [
            'h5p/classes/editor.php',
            'file_get_contents("{$CFG->dirroot}" . autoloader::get_h5p_editor_library_base($languagescript))',
            '@{autoloader::get_h5p_editor_library_base($languagescript)}'
        ];

        yield [
            'lib/externallib.php',
            'require($CFG->moodlepageclassfile)',
            '{$CFG->moodlepageclassfile}'
        ];

        yield [
            'lib/editor/atto/classes/plugininfo/atto.php',
            'require_once($this->full_path(\'settings.php\'))',
            '{$this->full_path(\'settings.php\')}'
        ];

        yield [
            'install.php',
            '$CFG->dirroot  = __DIR__',
            '@'
        ];

        yield [
            'admin/settings/plugins.php',
            'require_once($CFG->dirroot . \'/portfolio/\' . $portfolio->get(\'plugin\') . \'/lib.php\');',
            '@/portfolio/{$portfolio->get(\'plugin\')}/lib.php'
        ];

        yield [
            'lib/somelib.php',
            'require($CFG->dirroot . DIRECTORY_SEPARATOR . "config.php")',
            '@/config.php'
        ];

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
