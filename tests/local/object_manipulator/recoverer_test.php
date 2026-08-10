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

/**
 * Tests for object recoverer.
 *
 * @covers \tool_objectfs\local\object_manipulator\recoverer
 * @package   tool_objectfs
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class recoverer_test extends \tool_objectfs\tests\testcase {
    /** @var recoverer Recoverer object */
    protected $recoverer;

    protected function setUp(): void {
        parent::setUp();
        $config = manager::get_objectfs_config();
        manager::set_objectfs_config($config);
        $this->logger = new \tool_objectfs\log\aggregate_logger();
        $this->recoverer = new recoverer($this->filesystem, $config, $this->logger);
        ob_start();
    }

    protected function tearDown(): void {
        ob_end_clean();
        parent::tearDown();
    }

    public function test_recoverer_get_candidate_objects_will_get_error_objects(): void {
        $recovererobject = $this->create_error_object();
        $candidateobjects = recoverer::get_candidates(manager::get_objectfs_config());

        foreach ($candidateobjects as $candidate) {
            $this->assertEquals($recovererobject->contenthash, $candidate->contenthash);
        }
    }

    public function test_recoverer_will_recover_local_objects(): void {
        global $DB;
        $object = $this->create_local_object();
        manager::update_object_by_hash($object->contenthash, OBJECT_LOCATION_MISSING);

        $this->recoverer->execute([$object]);

        $location = manager::get_location_by_hash($object->contenthash);
        $this->assertEquals(OBJECT_LOCATION_LOCAL, $location);
    }

    public function test_recoverer_will_recover_duplicated_objects(): void {
        global $DB;
        $object = $this->create_duplicated_object();
        manager::update_object_by_hash($object->contenthash, OBJECT_LOCATION_MISSING);

        $this->recoverer->execute([$object]);

        $location = manager::get_location_by_hash($object->contenthash);
        $this->assertEquals(OBJECT_LOCATION_DUPLICATED, $location);
    }

    public function test_recoverer_will_recover_remote_objects(): void {
        global $DB;
        $object = $this->create_remote_object();
        manager::update_object_by_hash($object->contenthash, OBJECT_LOCATION_MISSING);

        $this->recoverer->execute([$object]);

        $location = manager::get_location_by_hash($object->contenthash);
        $this->assertEquals(OBJECT_LOCATION_EXTERNAL, $location);
    }

    public function test_recoverer_will_not_recover_error_objects(): void {
        global $DB;
        $object = $this->create_error_object();
        manager::update_object_by_hash($object->contenthash, OBJECT_LOCATION_MISSING);

        $this->recoverer->execute([$object]);

        $location = manager::get_location_by_hash($object->contenthash);
        $this->assertEquals(OBJECT_LOCATION_MISSING, $location);
    }
}
