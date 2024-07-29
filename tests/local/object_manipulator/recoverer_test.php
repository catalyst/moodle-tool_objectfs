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

namespace tool_objectfs\local\object_manipulator;

use tool_objectfs\local\manager;
use tool_objectfs\local\object_manipulator\candidates\candidates_finder;

/**
 * Tests for object recoverer.
 *
 * @covers \tool_objectfs\local\object_manipulator\recoverer
 * @package tool_objectfs
 */
class recoverer_test extends \tool_objectfs\tests\testcase {

    protected function setUp(): void {
        parent::setUp();
        $config = manager::get_objectfs_config();
        $this->candidatesfinder = new candidates_finder(recoverer::class, $config);
        manager::set_objectfs_config($config);
        $this->logger = new \tool_objectfs\log\aggregate_logger();
        $this->recoverer = new recoverer($this->filesystem, $config, $this->logger);
        ob_start();
    }

    protected function tearDown(): void {
        ob_end_clean();
    }

    public function test_recoverer_get_candidate_objects_will_get_error_objects() {
        $recovererobject = $this->create_error_object();
        $candidateobjects = $this->candidatesfinder->get();

        foreach ($candidateobjects as $candidate) {
            $this->assertEquals($recovererobject->contenthash, $candidate->contenthash);
        }
    }

    public function test_recoverer_will_recover_local_objects() {
        global $DB;
        $object = $this->create_local_object();
        $DB->set_field('tool_objectfs_objects', 'location', OBJECT_LOCATION_ERROR, ['contenthash' => $object->contenthash]);

        $this->recoverer->execute([$object]);

        $location = $DB->get_field('tool_objectfs_objects', 'location', ['contenthash' => $object->contenthash]);
        $this->assertEquals(OBJECT_LOCATION_LOCAL, $location);
    }

    public function test_recoverer_will_recover_duplicated_objects() {
        global $DB;
        $object = $this->create_duplicated_object();
        $DB->set_field('tool_objectfs_objects', 'location', OBJECT_LOCATION_ERROR, ['contenthash' => $object->contenthash]);

        $this->recoverer->execute([$object]);

        $location = $DB->get_field('tool_objectfs_objects', 'location', ['contenthash' => $object->contenthash]);
        $this->assertEquals(OBJECT_LOCATION_DUPLICATED, $location);
    }

    public function test_recoverer_will_recover_remote_objects() {
        global $DB;
        $object = $this->create_remote_object();
        $DB->set_field('tool_objectfs_objects', 'location', OBJECT_LOCATION_ERROR, ['contenthash' => $object->contenthash]);

        $this->recoverer->execute([$object]);

        $location = $DB->get_field('tool_objectfs_objects', 'location', ['contenthash' => $object->contenthash]);
        $this->assertEquals(OBJECT_LOCATION_EXTERNAL, $location);
    }

    public function test_recoverer_will_not_recover_error_objects() {
        global $DB;
        $object = $this->create_error_object();
        $DB->set_field('tool_objectfs_objects', 'location', OBJECT_LOCATION_ERROR, ['contenthash' => $object->contenthash]);

        $this->recoverer->execute([$object]);

        $location = $DB->get_field('tool_objectfs_objects', 'location', ['contenthash' => $object->contenthash]);
        $this->assertEquals(OBJECT_LOCATION_ERROR, $location);
    }
}
