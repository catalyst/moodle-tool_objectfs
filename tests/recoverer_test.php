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
use tool_objectfs\object_manipulator\recoverer;

require_once(__DIR__ . '/classes/test_client.php');
require_once(__DIR__ . '/tool_objectfs_testcase.php');

class recoverer_testcase extends tool_objectfs_testcase {

    protected function setUp() {
        parent::setUp();
        $config = get_objectfs_config();
        set_objectfs_config($config);
        $this->logger = new \tool_objectfs\log\aggregate_logger();
        $this->recoverer = new recoverer($this->filesystem, $config, $this->logger);
        ob_start();
    }

    protected function tearDown() {
        ob_end_clean();
    }

    public function test_recoverer_get_candidate_objects_will_get_error_objects() {
        $recovererobject = $this->create_error_object();

        $candidateobjects = $this->recoverer->get_candidate_objects();

        $this->assertArrayHasKey($recovererobject->contenthash, $candidateobjects);
    }

    public function test_recoverer_will_recover_local_objects() {
        global $DB;
        $object = $this->create_local_object();
        $DB->set_field('tool_objectfs_objects', 'location', OBJECT_LOCATION_ERROR, array('contenthash' => $object->contenthash));

        $this->recoverer->execute(array($object));

        $location = $DB->get_field('tool_objectfs_objects', 'location', array('contenthash' => $object->contenthash));
        $this->assertEquals(OBJECT_LOCATION_LOCAL, $location);
    }

    public function test_recoverer_will_recover_duplicated_objects() {
        global $DB;
        $object = $this->create_duplicated_object();
        $DB->set_field('tool_objectfs_objects', 'location', OBJECT_LOCATION_ERROR, array('contenthash' => $object->contenthash));

        $this->recoverer->execute(array($object));

        $location = $DB->get_field('tool_objectfs_objects', 'location', array('contenthash' => $object->contenthash));
        $this->assertEquals(OBJECT_LOCATION_DUPLICATED, $location);
    }

    public function test_recoverer_will_recover_remote_objects() {
        global $DB;
        $object = $this->create_remote_object();
        $DB->set_field('tool_objectfs_objects', 'location', OBJECT_LOCATION_ERROR, array('contenthash' => $object->contenthash));

        $this->recoverer->execute(array($object));

        $location = $DB->get_field('tool_objectfs_objects', 'location', array('contenthash' => $object->contenthash));
        $this->assertEquals(OBJECT_LOCATION_EXTERNAL, $location);
    }

    public function test_recoverer_will_not_recover_error_objects() {
        global $DB;
        $object = $this->create_error_object();
        $DB->set_field('tool_objectfs_objects', 'location', OBJECT_LOCATION_ERROR, array('contenthash' => $object->contenthash));

        $this->recoverer->execute(array($object));

        $location = $DB->get_field('tool_objectfs_objects', 'location', array('contenthash' => $object->contenthash));
        $this->assertEquals(OBJECT_LOCATION_ERROR, $location);
    }
}

