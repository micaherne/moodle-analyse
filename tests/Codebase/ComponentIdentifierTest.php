<?php

namespace MoodleAnalyse\Codebase;

use PHPUnit\Framework\TestCase;

class ComponentIdentifierTest extends TestCase
{
    public function testFileComponent()
    {
        $moodleroot = __DIR__ . '/../../moodle';
        if (!is_dir($moodleroot)) {
            $this->markTestSkipped("Moodle not found");
        }
        $componentIdentifier = new ComponentIdentifier($moodleroot);

        $this->assertEquals('mod_assign', $componentIdentifier->fileComponent('/mod/assign/locallib.php'));
        $this->assertEquals('assignsubmission_something',
            $componentIdentifier->fileComponent('/mod/assign/submission/something/locallib.php'));
        $this->assertEquals('atto_charmap',
            $componentIdentifier->fileComponent('lib/editor/atto/plugins/charmap/classes/privacy/provider.php'));
        $this->assertEquals('core_editor',
            $componentIdentifier->fileComponent('lib/editor/classes/privacy/provider.php'));
        $this->assertEquals('moodle',
            $componentIdentifier->fileComponent('lib/dml/moodle_recordset.php'));
        $this->assertEquals('moodle',
            $componentIdentifier->fileComponent('lib/formslib.php'));
    }


}
