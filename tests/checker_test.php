<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace tool_objectfs\tests;

defined('MOODLE_INTERNAL') || die();

use tool_objectfs\local\object_manipulator\checker;

require_once(__DIR__ . '/classes/test_client.php');
require_once(__DIR__ . '/tool_objectfs_testcase.php');

class checker_testcase extends tool_objectfs_testcase {

    /** @var string $manipulator */
    protected $manipulator = checker::class;

    protected function setUp() {
        parent::setUp();
        $config = get_objectfs_config();
        set_objectfs_config($config);
        $this->logger = new \tool_objectfs\log\aggregate_logger();
        $this->checker = new checker($this->filesystem, $config, $this->logger);
        ob_start();
    }

    protected function tearDown() {
        ob_end_clean();
    }

    public function test_checker_get_location_local_if_object_is_local() {
        global $DB;
        $file = $this->create_local_object();
        $location = $DB->get_field('tool_objectfs_objects', 'location', array('contenthash' => $file->contenthash));
        $this->assertNotFalse($location);
        $this->assertEquals(OBJECT_LOCATION_LOCAL, $location);
    }

    public function test_checker_get_location_duplicated_if_object_is_duplicated() {
        global $DB;
        $file = $this->create_duplicated_object();
        $location = $DB->get_field('tool_objectfs_objects', 'location', array('contenthash' => $file->contenthash));
        $this->assertNotFalse($location);
        $this->assertEquals(OBJECT_LOCATION_DUPLICATED, $location);
    }

    public function test_checker_get_location_external_if_object_is_external() {
        global $DB;
        $file = $this->create_remote_object();
        $location = $DB->get_field('tool_objectfs_objects', 'location', array('contenthash' => $file->contenthash));
        $this->assertNotFalse($location);
        $this->assertEquals(OBJECT_LOCATION_EXTERNAL, $location);
    }

    public function test_checker_get_candidate_objects_will_not_get_objects() {
        $localobject = $this->create_local_object('test_checker_get_candidate_objects_will_not_get_objects_local');
        $remoteobject = $this->create_remote_object('test_checker_get_candidate_objects_will_not_get_objects_remote');
        $duplicatedbject = $this->create_duplicated_object('test_checker_get_candidate_objects_will_not_get_objects_duplicated');

        self::assertFalse($this->objects_contain_hash($localobject->contenthash));
        self::assertFalse($this->objects_contain_hash($remoteobject->contenthash));
        self::assertFalse($this->objects_contain_hash($duplicatedbject->contenthash));
    }

    public function test_checker_get_candidate_objects_will_get_object() {
        global $DB;
        $localobject = $this->create_local_object('test_checker_get_candidate_objects_will_get_object');
        $DB->delete_records('tool_objectfs_objects', array('contenthash' => $localobject->contenthash));
        $candidateobjects = $this->candidatesfinder->get();

        $this->assertNotCount(0, $candidateobjects);
        self::assertTrue($this->objects_contain_hash($localobject->contenthash));
    }

    public function test_checker_can_update_object() {
        global $DB;
        $localobject = $this->create_local_object('test_checker_can_update_object');
        $localobject->id = null;
        $DB->delete_records('tool_objectfs_objects', ['contenthash' => $localobject->contenthash]);
        $this->checker->execute([$localobject]);
        $dblocation = $DB->get_field('tool_objectfs_objects', 'location', ['contenthash' => $localobject->contenthash]);

        $this->assertNotFalse($dblocation);
        $this->assertEquals(OBJECT_LOCATION_LOCAL, $dblocation);
        self::assertFalse($this->objects_contain_hash($localobject->contenthash));
    }

    public function test_checker_manipulate_object_method_will_get_correct_location_if_file_is_local() {
        $file = $this->create_local_object();
        $reflection = new \ReflectionMethod(checker::class, "manipulate_object");
        $reflection->setAccessible(true);
        $this->assertEquals(OBJECT_LOCATION_LOCAL, $reflection->invokeArgs($this->checker, array($file)));
    }

    public function test_checker_manipulate_object_method_will_get_correct_location_if_file_is_duplicated() {
        $file = $this->create_duplicated_object();
        $reflection = new \ReflectionMethod(checker::class, "manipulate_object");
        $reflection->setAccessible(true);
        $this->assertEquals(OBJECT_LOCATION_DUPLICATED, $reflection->invokeArgs($this->checker, array($file)));
    }

    public function test_checker_manipulate_object_method_will_get_correct_location_if_file_is_external() {
        $file = $this->create_remote_object();
        $reflection = new \ReflectionMethod(checker::class, "manipulate_object");
        $reflection->setAccessible(true);
        $this->assertEquals(OBJECT_LOCATION_EXTERNAL, $reflection->invokeArgs($this->checker, array($file)));
    }

    public function test_checker_manipulate_object_method_will_get_error_location_on_error_file() {
        $file = $this->create_error_object();
        $reflection = new \ReflectionMethod(checker::class, "manipulate_object");
        $reflection->setAccessible(true);
        $this->assertEquals(OBJECT_LOCATION_ERROR, $reflection->invokeArgs($this->checker, array($file)));
    }
}
