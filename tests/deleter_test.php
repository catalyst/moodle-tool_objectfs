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

use tool_objectfs\object_file_system;
use tool_objectfs\object_manipulator\deleter;

require_once(__DIR__ . '/classes/test_client.php');
require_once(__DIR__ . '/tool_objectfs_testcase.php');

class deleter_testcase extends tool_objectfs_testcase {

    protected function setUp() {
        parent::setUp();
        $config = get_objectfs_config();
        $config->deletelocal = true;
        $config->consistencydelay = 0;
        $config->sizethreshold = 0;
        set_objectfs_config($config);
        $this->logger = new \tool_objectfs\log\aggregate_logger();
        $this->deleter = new deleter($this->filesystem, $config, $this->logger);
        ob_start();
    }

    protected function tearDown() {
        ob_end_clean();
    }

    protected function set_deleter_config($key, $value) {
        $config = get_objectfs_config();
        $config->$key = $value;
        $this->deleter = new deleter($this->filesystem, $config, $this->logger);
    }

    public function test_deleter_get_candidate_objects_will_get_duplicated_objects() {
        $duplicatedbject = $this->create_duplicated_object();

        $candidateobjects = $this->deleter->get_candidate_objects();

        $this->assertArrayHasKey($duplicatedbject->contenthash, $candidateobjects);
    }

    public function test_deleter_get_candidate_objects_will_not_get_local_or_remote_objects() {
        $localobject = $this->create_local_object();
        $remoteobject = $this->create_remote_object();

        $candidateobjects = $this->deleter->get_candidate_objects();

        $this->assertArrayNotHasKey($localobject->contenthash, $candidateobjects);
        $this->assertArrayNotHasKey($remoteobject->contenthash, $candidateobjects);
    }

    public function test_deleter_get_candidate_objects_will_not_get_objects_which_havent_been_duplicated_for_consistancy_delay() {
        $duplicatedbject = $this->create_duplicated_object();
        $this->set_deleter_config('consistencydelay', 100);

        $candidateobjects = $this->deleter->get_candidate_objects();

        $this->assertArrayNotHasKey($duplicatedbject->contenthash, $candidateobjects);
    }

    public function test_deleter_get_candidate_objects_will_return_no_objects_if_deletelocal_disabled() {
        $duplicatedbject = $this->create_duplicated_object();
        $this->set_deleter_config('deletelocal', 0);

        $candidateobjects = $this->deleter->get_candidate_objects();

        $this->assertArrayNotHasKey($duplicatedbject->contenthash, $candidateobjects);
    }

    public function test_deleter_can_delete_object() {
        global $DB;
        $object = $this->create_duplicated_object();

        $this->deleter->execute(array($object));

        $location = $DB->get_field('tool_objectfs_objects', 'location', array('contenthash' => $object->contenthash));
        $this->assertEquals(OBJECT_LOCATION_EXTERNAL, $location);
        $this->assertFalse($this->is_locally_readable_by_hash($object->contenthash));
        $this->assertTrue($this->is_externally_readable_by_hash($object->contenthash));
    }

    public function test_deleter_can_handle_local_object() {
        global $DB;
        $object = $this->create_local_object();

        $this->deleter->execute(array($object));

        $location = $DB->get_field('tool_objectfs_objects', 'location', array('contenthash' => $object->contenthash));
        $this->assertEquals(OBJECT_LOCATION_LOCAL, $location);
        $this->assertTrue($this->is_locally_readable_by_hash($object->contenthash));
        $this->assertFalse($this->is_externally_readable_by_hash($object->contenthash));
    }

    public function test_deleter_can_handle_remote_object() {
        global $DB;
        $object = $this->create_remote_object();

        $this->deleter->execute(array($object));

        $location = $DB->get_field('tool_objectfs_objects', 'location', array('contenthash' => $object->contenthash));
        $this->assertEquals(OBJECT_LOCATION_EXTERNAL, $location);
        $this->assertFalse($this->is_locally_readable_by_hash($object->contenthash));
        $this->assertTrue($this->is_externally_readable_by_hash($object->contenthash));
    }

    public function test_deleter_will_delete_no_objects_if_deletelocal_disabled() {
        global $DB;
        $object = $this->create_duplicated_object();
        $this->set_deleter_config('deletelocal', 0);

        $this->deleter->execute(array($object));

        $location = $DB->get_field('tool_objectfs_objects', 'location', array('contenthash' => $object->contenthash));
        $this->assertEquals(OBJECT_LOCATION_DUPLICATED, $location);
        $this->assertTrue($this->is_locally_readable_by_hash($object->contenthash));
        $this->assertTrue($this->is_externally_readable_by_hash($object->contenthash));
    }

    public function test_deleter_can_delete_multiple_objects() {
        global $DB;
        $objects = array();
        for ($i = 0; $i < 5; $i++) {
            $objects[] = $this->create_duplicated_object("Object $i");
        }

        $this->deleter->execute($objects);

        foreach ($objects as $object) {
            $location = $DB->get_field('tool_objectfs_objects', 'location', array('contenthash' => $object->contenthash));
            $this->assertEquals(OBJECT_LOCATION_EXTERNAL, $location);
            $this->assertFalse($this->is_locally_readable_by_hash($object->contenthash));
            $this->assertTrue($this->is_externally_readable_by_hash($object->contenthash));
        }
    }
}

