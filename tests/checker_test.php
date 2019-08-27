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

    protected function setUp() {
        parent::setUp();
        $config = get_objectfs_config();
        // $config->sizethreshold = 100;
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
        $fslocation = $this->filesystem->get_object_location_from_hash($file->contenthash);
        $dblocation = $DB->get_field('tool_objectfs_objects', 'location', array('contenthash' => $file->contenthash));

        $this->assertEquals(OBJECT_LOCATION_LOCAL, $fslocation);
        $this->assertEquals(OBJECT_LOCATION_LOCAL, $dblocation);
        $this->assertIsNotBool($dblocation);
    }

    public function test_checker_get_location_duplicated_if_object_is_duplicated() {
        global $DB;
        $file = $this->create_duplicated_object();
        $fslocation = $this->filesystem->get_object_location_from_hash($file->contenthash);
        $dblocation = $DB->get_field('tool_objectfs_objects', 'location', array('contenthash' => $file->contenthash));

        $this->assertEquals(OBJECT_LOCATION_DUPLICATED, $fslocation);
        $this->assertEquals(OBJECT_LOCATION_DUPLICATED, $dblocation);
        $this->assertIsNotBool($dblocation);
    }

    public function test_checker_get_location_external_if_object_is_external() {
        global $DB;
        $file = $this->create_remote_object();
        $fslocation = $this->filesystem->get_object_location_from_hash($file->contenthash);
        $dblocation = $DB->get_field('tool_objectfs_objects', 'location', array('contenthash' => $file->contenthash));

        $this->assertEquals(OBJECT_LOCATION_EXTERNAL, $fslocation);
        $this->assertEquals(OBJECT_LOCATION_EXTERNAL, $dblocation);
        $this->assertIsNotBool($dblocation);
    }

    public function test_checker_get_candidate_objects_will_not_get_objects() {
        $localobject = $this->create_local_object();
        $remoteobject = $this->create_remote_object();
        $duplicatedbject = $this->create_duplicated_object();
        $candidateobjects = $this->checker->get_candidate_objects();

        $this->assertArrayNotHasKey($localobject->contenthash, $candidateobjects);
        $this->assertArrayNotHasKey($remoteobject->contenthash, $candidateobjects);
        $this->assertArrayNotHasKey($duplicatedbject->contenthash, $candidateobjects);
        $this->assertCount(0, $candidateobjects);
    }

    public function test_checker_get_candidate_objects_will_get_object() {
        global $DB;
        $localobject = $this->create_local_object();
        $DB->delete_records('tool_objectfs_objects', array('contenthash' => $localobject->contenthash));
        $candidateobjects = $this->checker->get_candidate_objects();

        $this->assertNotCount(0, $candidateobjects);
        foreach ($candidateobjects as $candidate) {
            $this->assertEquals($localobject->contenthash, $candidate->contenthash);
        }
    }

    public function test_checker_can_update_object() {
        global $DB;
        $localobject = $this->create_local_object();
        $DB->delete_records('tool_objectfs_objects', array('contenthash' => $localobject->contenthash));
        $this->checker->execute(array($localobject));
        $candidateobjects = $this->checker->get_candidate_objects();
        $dblocation = $DB->get_field('tool_objectfs_objects', 'location', array('contenthash' => $localobject->contenthash));

        $this->assertCount(0, $candidateobjects);
        $this->assertEquals(OBJECT_LOCATION_LOCAL, $dblocation);
        $this->assertIsNotBool($dblocation);
    }
}
