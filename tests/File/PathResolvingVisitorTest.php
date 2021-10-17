<?php

namespace MoodleAnalyse\File;

use MoodleAnalyse\Visitor\IncludeResolvingVisitor;
use MoodleAnalyse\Visitor\PathFindingVisitor;
use MoodleAnalyse\Visitor\PathResolvingVisitor;
use PhpParser\Node\Scalar\Encapsed;
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

        $pathNodes = $this->getPathNodes($path, $require);

        $this->assertEquals($expected, $pathNodes[0]->getAttribute(IncludeResolvingVisitor::RESOLVED_INCLUDE), $message);

    }

    public function coreComponentCheckingProvider()
    {

        yield [
            'question/engine/tests/helpers.php',
            <<<'EOD'
            $file = core_component::get_plugin_directory('qtype', $qtype) . '/tests/helper.php';
            include_once($file);
            EOD,
            null
        ];

        yield [
            'backup/util/plan/backup_structure_step.class.php',
            <<<'EOD'
            $parentfile = core_component::get_component_directory($plugintype . '_' . $pluginname) .
            '/backup/moodle2/' . $parentclass . '.class.php';
            require($parentfile);
            EOD,
            null
        ];

        yield [
            'admin/settings.php',
            <<<'EOD'
            foreach (core_component::get_plugin_list('report') as $report => $plugindir) {
                $settings_path = "$plugindir/settings.php";
                require($settings_path);
            }
            EOD,
            null
        ];

        yield [
            'backup/util/plan/backup_structure_step.class.php',
            <<<'EOD'
            $pluginsdirs = core_component::get_plugin_list($plugintype);
            foreach ($pluginsdirs as $name => $plugindir) {
                $classname = 'backup_' . $plugintype . '_' . $name . '_plugin';
                $backupfile = $plugindir . '/backup/moodle2/' . $classname . '.class.php';
                if (file_exists($backupfile)) {
                    require_once($backupfile);
                }
            }
            EOD,
            null
        ];

        yield [
            'lib/portfoliolib.php',
            <<<'EOD'
            if (!$componentloc = core_component::get_component_directory($component)) {
                throw new portfolio_button_exception('nocallbackcomponent', 'portfolio', '', $component);
            }
            require_once($componentloc . '/locallib.php');
            EOD,
            null
        ];

        yield [
            'lib/accesslib.php',
            <<<'EOD'
            $base = core_component::get_plugin_directory($type, $name);
            include("{$base}/db/subplugins.php");
            EOD,
            null
        ];

        yield [
            '',
            <<<'EOD'
            $plugins = \core_component::get_plugin_list('cachestore');
                if (!array_key_exists($plugin, $plugins)) {
                    throw new \coding_exception('Invalid cache plugin used when trying to create an edit form.');
                }
                $plugindir = $plugins[$plugin];
                $class = 'cachestore_addinstance_form';
                if (file_exists($plugindir.'/addinstanceform.php')) {
                    require_once($plugindir.'/addinstanceform.php');
                }
            EOD,
            null

        ];

        yield [
            'mod/example/lib.php',
            <<<'EOD'
            foreach (core_component::get_plugin_list('mod') as $plugindir) {
                require_once($plugindir . '/lib.php');
            }
            EOD,
            null

        ];

        yield [
            'mod/example/lib.php',
            <<<'EOD'
            $plugins = core_component::get_plugin_list('mod');
            foreach ($plugins as $plugindir) {
                require_once($plugindir . '/lib.php');
            }
            EOD,
            null

        ];

        yield [
            'mod/example/lib.php',
            <<<'EOD'
            $plugins = core_component::get_plugin_list('mod');
            require_once($plugins['name'] . '/lib.php');
            EOD,
            null

        ];

        yield [
            'mod/example/lib.php',
            <<<'EOD'
            $plugins = core_component::get_plugin_list('mod');
            $name = 'name';
            require_once($plugins[$name] . '/lib.php');
            EOD,
            null

        ];

    }

    /**
     * @dataProvider coreComponentCheckingProvider
     * @param string $path
     * @param string $code
     * @param callable $check
     */
    public function testCoreComponentChecking(string $path, string $code, ?callable $check)
    {
        $visitor = new PathResolvingVisitor();
        $visitor->setFilePath($path);

        $this->traverser->addVisitor($visitor);
        $nodes = $this->parser->parse("<?php {$code}");

        $this->preProcessor->addVisitor($this->parentConnectingVisitor);
        $this->preProcessor->addVisitor(new PathFindingVisitor());
        $nodes = $this->preProcessor->traverse($nodes);

        $this->traverser->traverse($nodes);

        $pathNodes = $visitor->getPathNodes();

        if (is_null($check)) {
            $check = function ($pathNodes) {
                $require = $pathNodes[0];
                $attribute = $require->getAttribute('fromCoreComponent');
                $this->assertTrue($attribute);
            };
        }

        $check($pathNodes);
    }

    public function requireDataProvider(): \Generator
    {

        yield [
            'repository/upload/tests/behat/behat_repository_upload.php',
            '$filepath = $CFG->dirroot . DIRECTORY_SEPARATOR . $CFG->admin .
                    DIRECTORY_SEPARATOR . substr($filepath, 6)',
            '@{DIRECTORY_SEPARATOR}admin{DIRECTORY_SEPARATOR}{substr($filepath, 6)}'
        ];

        // We're never going to rewrite core_component but it would be good if this structure worked.
        yield [
            'lib/classes/component.php',
            '$CFG->dirroot . self::$classmap[$classname]',
            '@{self::$classmap[$classname]}'
        ];

        // Constants that aren't DIRECTORY_SEPARATOR should just be passed through as code.
        yield [
            'lib/behat/classes/behat_config_manager.php',
            '$CFG->dirroot . \'/\' . BEHAT_PARALLEL_SITE_NAME . $i',
            '@/{BEHAT_PARALLEL_SITE_NAME}{$i}'
        ];

        // Breaking out of dirroot to find the default datadir.
        yield [
            'admin/cli/install',
            'dirname(dirname(dirname(__DIR__))).\'/moodledata\'',
            '@/../moodledata'
        ];

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
            '@{DIRECTORY_SEPARATOR}config.php'
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
        while ($row = fgetcsv($in)) {
            yield [$row[0], 'require_once' . $row[1], $row[2]];
        }
        fclose($in);
    }

    public function testMarkAsConfigInclude() {
        $nodes = $this->getPathNodes('admin/test.php', 'require(__DIR__ . \'/../config.php\');');
        $this->assertTrue($nodes[0]->getAttribute('parent')->getAttribute(PathResolvingVisitor::IS_CONFIG_INCLUDE));
    }

    /**
     * @param $path
     * @param $require
     * @return \PhpParser\Node[]
     */
    private function getPathNodes($path, $code): array
    {
        $visitor = new PathResolvingVisitor();
        $visitor->setFilePath($path);

        $this->traverser->addVisitor($visitor);
        $nodes = $this->parser->parse("<?php $code;");

        $this->preProcessor->addVisitor($this->parentConnectingVisitor);
        $this->preProcessor->addVisitor(new PathFindingVisitor());
        $nodes = $this->preProcessor->traverse($nodes);

        $this->traverser->traverse($nodes);

        $pathNodes = $visitor->getPathNodes();
        return $pathNodes;
    }

}
