<?php

namespace MoodleAnalyse\Codebase;

use Exception;
use Generator;
use PHPUnit\Framework\TestCase;

class ComponentResolverTest extends TestCase
{

    /*public function testFake()
    {
        $out = fopen(__DIR__ . '/../fixtures/resolved-components.csv', 'w');
        $in = fopen(__DIR__ . '/../../relative-paths.csv', 'r');
        fgetcsv($in);
        $done = [];
        $componentResolver = new ComponentResolver(__DIR__ . '/../../moodle');
        while ($row = fgetcsv($in)) {
            $resolvedInclude = $row[4];
            if (array_key_exists($resolvedInclude, $done)) {
                continue;
            }
            $resolvedComponent = $componentResolver->resolveComponent($resolvedInclude);
            $done[$resolvedInclude] = true;
            if (is_null($resolvedComponent)) {
                fputcsv($out, [$resolvedInclude]);
            } else {
                fputcsv($out, [$resolvedInclude, ...$resolvedComponent]);
            }
        }
        fclose($in);
        fclose($out);
    }

    public function testFake()
    {
        $componentResolver = new ComponentResolver(__DIR__ . '/../../moodle');
        $componentsJsonData = json_decode(file_get_contents(__DIR__ . '/../../moodle/lib/components.json'));
        $class = new \ReflectionClass($componentResolver);
        $method = $class->getMethod('getSubpluginData');
        $method->setAccessible(true);
        $data = $method->invoke($componentResolver, $componentsJsonData);
        $result = [];
        foreach ($data as $key => $item) {
            $result[$key] = $item;
        }
        echo var_export($result);
    }*/

    /**
     * @dataProvider resolveComponentData
     * @param string $resolvedInclude
     * @param array|null $expected
     * @throws Exception
     */
    public function testResolveComponent(string $resolvedInclude, ?array $expected)
    {
        $componentResolver = new class(__DIR__ . '/../../moodle') extends ComponentResolver {
            public function __construct(string $moodleroot)
            {
                parent::__construct($moodleroot);
                $this->componentsJsonLocation = __DIR__ . '/../fixtures/components.json';
            }

            protected function getSubpluginData(object $componentsJsonData): iterable
            {
                return [
                    'mod_assign' =>
                        (object)[
                            'plugintypes' =>
                                (object)[
                                    'assignsubmission' => 'mod/assign/submission',
                                    'assignfeedback' => 'mod/assign/feedback',
                                ],
                        ],
                    'mod_assignment' =>
                        (object)[
                            'plugintypes' =>
                                (object)[
                                    'assignment' => 'mod/assignment/type',
                                ],
                        ],
                    'mod_book' =>
                        (object)[
                            'plugintypes' =>
                                (object)[
                                    'booktool' => 'mod/book/tool',
                                ],
                        ],
                    'mod_data' =>
                        (object)[
                            'plugintypes' =>
                                (object)[
                                    'datafield' => 'mod/data/field',
                                    'datapreset' => 'mod/data/preset',
                                ],
                        ],
                    'mod_forum' =>
                        (object)[
                            'plugintypes' =>
                                (object)[
                                    'forumreport' => 'mod/forum/report',
                                ],
                        ],
                    'mod_lti' =>
                        (object)[
                            'plugintypes' =>
                                (object)[
                                    'ltisource' => 'mod/lti/source',
                                    'ltiservice' => 'mod/lti/service',
                                ],
                        ],
                    'mod_quiz' =>
                        (object)[
                            'plugintypes' =>
                                (object)[
                                    'quiz' => 'mod/quiz/report',
                                    'quizaccess' => 'mod/quiz/accessrule',
                                ],
                        ],
                    'mod_scorm' =>
                        (object)[
                            'plugintypes' =>
                                (object)[
                                    'scormreport' => 'mod/scorm/report',
                                ],
                        ],
                    'mod_workshop' =>
                        (object)[
                            'plugintypes' =>
                                (object)[
                                    'workshopform' => 'mod/workshop/form',
                                    'workshopallocation' => 'mod/workshop/allocation',
                                    'workshopeval' => 'mod/workshop/eval',
                                ],
                        ],
                    'editor_atto' =>
                        (object)[
                            'plugintypes' =>
                                (object)[
                                    'atto' => 'lib/editor/atto/plugins',
                                ],
                        ],
                    'editor_tinymce' =>
                        (object)[
                            'plugintypes' =>
                                (object)[
                                    'tinymce' => 'lib/editor/tinymce/plugins',
                                ],
                        ],
                    'tool_log' =>
                        (object)[
                            'plugintypes' =>
                                (object)[
                                    'logstore' => 'admin/tool/log/store',
                                ],
                        ],
                ];
            }


        };

        $this->assertEquals($expected, $componentResolver->resolveComponent($resolvedInclude));
    }

    public function resolveComponentData(): Generator
    {
        yield ['@/lib//questionlib.php', ['core', null, 'questionlib.php']];
        yield ['@/cache/stores', ['core', 'cache', 'stores']];
        yield ['@/lib', ['core', null, '']];
        yield ['@/auth/db/classes/privacy/provider.php', ['auth', 'db', 'classes/privacy/provider.php']];
        yield ['@/mod/db/test.php', ['core', 'root', 'mod/db/test.php']];
        yield ['@/mod/assign/feedback/index.php', ['mod', 'assign', 'feedback/index.php']];
        yield ['@/mod/assign/feedback/db/index.php', ['mod', 'assign', 'feedback/db/index.php']];
        yield ['@/mod/assign/feedback/offline/index.php', ['assignfeedback', 'offline', 'index.php']];
        yield ['@/mod/assign/feedback/offline/db/install.xml', ['assignfeedback', 'offline', 'db/install.xml']];
        yield ['@/mod/assign/backup/moodle2', ['mod', 'assign', 'backup/moodle2']];
        yield ['@/mod/assign/backup/moodle2/', ['mod', 'assign', 'backup/moodle2/']];
        yield ['@/mod/assign/backup', ['mod', 'assign', 'backup']];
        yield ['@/blocks/classes/external.php', ['core', 'block', 'classes/external.php']];
        yield ['@/blocks/classes/external', ['core', 'block', 'classes/external']];
        yield [
            '@/blocks/classes/external/fetch_addable_blocks.php',
            ['core', 'block', 'classes/external/fetch_addable_blocks.php']
        ];
        yield ['@/blocks/activity_modules/db/access.php', ['block', 'activity_modules', 'db/access.php']];
        yield ['@/blocks/activity_modules', ['block', 'activity_modules', '']];
        yield ['@/blocks/activity_modules/', ['block', 'activity_modules', '/']];
        yield ['@/blocks/activity_modules/db/', ['block', 'activity_modules', 'db/']];
        yield ['@/blocks/edit_form.php', ['core', 'block', 'edit_form.php']];
        yield ['@/blocks/{$blockname}/block_{$blockname}.php', ['block', '{$blockname}', 'block_{$blockname}.php']];
        yield ['@/report/{$plugin}', ['report', '{$plugin}', '']];
        yield ['@/report/{$plugin}/', ['report', '{$plugin}', '/']];
        yield ['{$path}', null];

        $in = fopen(__DIR__ . '/../fixtures/resolved-components.csv', 'r');
        while ($row = fgetcsv($in)) {
            if (!empty($row[4])) {
                continue;
            }
            $resolvedInclude = array_shift($row);
            if (count($row) === 0 || empty($row[0])) {
                yield [$resolvedInclude, null];
            } else {
                yield [$resolvedInclude, array_slice($row, 0, 3)];
            }
        }
    }
}
