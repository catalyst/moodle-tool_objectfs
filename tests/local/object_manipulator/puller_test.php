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
 * Tests for object puller.
 *
 * @covers \tool_objectfs\local\object_manipulator\puller
 * @package tool_objectfs
 * @copyright Catalyst IT
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class puller_test extends \tool_objectfs\tests\testcase {

    /** @var string $manipulator */
    protected $manipulator = puller::class;

    /** @var puller Puller object */
    protected $puller;

    protected function setUp(): void {
        parent::setUp();
        $config = manager::get_objectfs_config();
        $config->sizethreshold = 100;
        manager::set_objectfs_config($config);
        $this->logger = new \tool_objectfs\log\aggregate_logger();
        $this->puller = new puller($this->filesystem, $config, $this->logger);
        ob_start();
    }

    protected function tearDown(): void {
        ob_end_clean();
    }

    /**
     * set_puller_config
     * @param mixed $key
     * @param mixed $value
     *
     * @return void
     */
    protected function set_puller_config($key, $value) {
        $config = manager::get_objectfs_config();
        $config->$key = $value;
        manager::set_objectfs_config($config);
        $this->puller = new puller($this->filesystem, $config, $this->logger);
    }

    public function test_puller_get_candidate_objects_will_get_remote_objects() {
        $remoteobject = $this->create_remote_object();

        self::assertTrue($this->objects_contain_hash($remoteobject->contenthash));
    }

    public function test_puller_get_candidate_objects_will_not_get_duplicated_or_local_objects() {
        $localobject = $this->create_local_object();
        $duplicatedobject = $this->create_duplicated_object();

        self::assertFalse($this->objects_contain_hash($localobject->contenthash));
        self::assertFalse($this->objects_contain_hash($duplicatedobject->contenthash));
    }

    public function test_puller_get_candidate_objects_will_not_get_objects_over_sizethreshold() {
        global $DB;
        $remoteobject = $this->create_remote_object();
        $DB->set_field('files', 'filesize', 10, ['contenthash' => $remoteobject->contenthash]);
        $this->set_puller_config('sizethreshold', 0);

        self::assertFalse($this->objects_contain_hash($remoteobject->contenthash));
    }

    public function test_puller_can_pull_remote_file() {
        global $DB;
        $object = $this->create_remote_object();

        $this->puller->execute([$object]);

        $location = $DB->get_field('tool_objectfs_objects', 'location', ['contenthash' => $object->contenthash]);
        $this->assertEquals(OBJECT_LOCATION_DUPLICATED, $location);
        $this->assertTrue($this->is_locally_readable_by_hash($object->contenthash));
        $this->assertTrue($this->is_externally_readable_by_hash($object->contenthash));
    }

    public function test_puller_can_handle_duplicated_file() {
        global $DB;
        $object = $this->create_duplicated_object();

        $this->puller->execute([$object]);

        $location = $DB->get_field('tool_objectfs_objects', 'location', ['contenthash' => $object->contenthash]);
        $this->assertEquals(OBJECT_LOCATION_DUPLICATED, $location);
        $this->assertTrue($this->is_locally_readable_by_hash($object->contenthash));
        $this->assertTrue($this->is_externally_readable_by_hash($object->contenthash));
    }

    public function test_puller_can_handle_local_file() {
        global $DB;
        $object = $this->create_local_object();

        $this->puller->execute([$object]);

        $location = $DB->get_field('tool_objectfs_objects', 'location', ['contenthash' => $object->contenthash]);
        $this->assertEquals(OBJECT_LOCATION_LOCAL, $location);
        $this->assertTrue($this->is_locally_readable_by_hash($object->contenthash));
        $this->assertFalse($this->is_externally_readable_by_hash($object->contenthash));
    }
}
