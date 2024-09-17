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
 * Tests for object orphaner.
 *
 * @covers \tool_objectfs\local\object_manipulator\orphaner
 * @package   tool_objectfs
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class orphaner_test extends \tool_objectfs\tests\testcase {

    /** @var string $manipulator */
    protected $manipulator = orphaner::class;

    /** @var orphaner Orphaner object */
    protected $orphaner;

    protected function setUp(): void {
        parent::setUp();
        $config = manager::get_objectfs_config();
        $config->sizethreshold = 0;
        $config->minimumage = 0;
        manager::set_objectfs_config($config);
        $this->logger = new \tool_objectfs\log\aggregate_logger();
        $this->orphaner = new orphaner($this->filesystem, $config, $this->logger);
        ob_start();
    }

    protected function tearDown(): void {
        ob_end_clean();
    }

    /**
     * set_orphaner_config
     * @param mixed $key
     * @param mixed $value
     *
     * @return void
     */
    protected function set_orphaner_config($key, $value) {
        $config = manager::get_objectfs_config();
        $config->$key = $value;
        manager::set_objectfs_config($config);
        $this->orphaner = new orphaner($this->filesystem, $config, $this->logger);
    }

    public function test_orphaner_can_orphan_files() {
        global $DB;
        $this->orphaner->execute([
            $object1 = $this->create_local_object(),
            $object2 = $this->create_duplicated_object(),
            $object3 = $this->create_remote_object(),
        ]);
        $location1 = $DB->get_field('tool_objectfs_objects', 'location', ['contenthash' => $object1->contenthash]);
        $location2 = $DB->get_field('tool_objectfs_objects', 'location', ['contenthash' => $object2->contenthash]);
        $location3 = $DB->get_field('tool_objectfs_objects', 'location', ['contenthash' => $object3->contenthash]);
        $this->assertEquals(OBJECT_LOCATION_ORPHANED, $location1);
        $this->assertEquals(OBJECT_LOCATION_ORPHANED, $location2);
        $this->assertEquals(OBJECT_LOCATION_ORPHANED, $location3);
    }

    public function test_orphaner_finds_correct_candidates() {
        global $DB;

        // Initialise the candidate finder.
        $config = manager::get_objectfs_config();
        $config->filesystem = get_class($this->filesystem);
        $finder = new candidates_finder($this->manipulator, $config);
        $objects = $finder->get();
        $this->assertCount(0, $objects); // No candidates.

        // Create an object.
        $object = $this->create_local_object();

        // Still no candidates - object created but nothing is missing from {files} table.
        $objects = $finder->get();
        $this->assertCount(0, $objects);

        // Update that object to have a different hash, to mock a non-existent
        // mdl_file with an objectfs record (orphaned).
        $DB->set_field('files', 'contenthash', 'different', ['contenthash' => $object->contenthash]);

        // Expect one candidate - no matching contenthash in {files}.
        $objects = $finder->get();
        $this->assertCount(1, $objects);

        // Ensure it ignores orphaned records during the find.
        $DB->set_field('tool_objectfs_objects', 'location', OBJECT_LOCATION_ORPHANED, ['contenthash' => $object->contenthash]);
        $objects = $finder->get();
        $this->assertCount(0, $objects); // No candidates - only candidate has been orphaned.
    }

    public function test_orphaner_correctly_orphans_provided_files() {
        global $DB;
        $this->orphaner->execute([
            $object1 = $this->create_local_object(),
            $object2 = $this->create_duplicated_object(),
            $object3 = $this->create_remote_object(),
        ]);
        $location1 = $DB->get_field('tool_objectfs_objects', 'location', ['contenthash' => $object1->contenthash]);
        $location2 = $DB->get_field('tool_objectfs_objects', 'location', ['contenthash' => $object2->contenthash]);
        $location3 = $DB->get_field('tool_objectfs_objects', 'location', ['contenthash' => $object3->contenthash]);
        $this->assertEquals(OBJECT_LOCATION_ORPHANED, $location1);
        $this->assertEquals(OBJECT_LOCATION_ORPHANED, $location2);
        $this->assertEquals(OBJECT_LOCATION_ORPHANED, $location3);
    }

}
