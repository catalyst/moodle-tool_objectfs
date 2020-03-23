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

use tool_objectfs\local\object_manipulator\candidates\candidates_finder;
use tool_objectfs\local\object_manipulator\pusher;

require_once(__DIR__ . '/classes/config.php');
require_once(__DIR__ . '/classes/test_client.php');
require_once(__DIR__ . '/tool_objectfs_testcase.php');

class pusher_testcase extends tool_objectfs_testcase {

    /** @var string $manipulator */
    protected $manipulator = pusher::class;

    protected function setUp() {
        parent::setUp();
        $config['sizethreshold'] = 0;
        $config['minimumage'] = 0;
        config::set_config($config);
        $this->logger = new \tool_objectfs\log\aggregate_logger();
        $this->pusher = new pusher($this->filesystem, config::instance(), $this->logger);
        ob_start();
    }

    protected function tearDown() {
        ob_end_clean();
    }

    protected function set_pusher_config($key, $value) {
        config::set_config([$key => $value]);
        $this->pusher = new pusher($this->filesystem, config::instance(), $this->logger);
    }

    public function test_pusher_get_candidate_objects_will_get_local_objects() {
        $object = $this->create_local_object();

        self::assertTrue($this->objects_contain_hash($object->contenthash));;
    }

    public function test_pusher_get_candidate_objects_wont_get_duplicated_or_remote_objects() {
        $duplicatedobject = $this->create_duplicated_object();
        $remoteobject = $this->create_remote_object();

        self::assertFalse($this->objects_contain_hash($duplicatedobject->contenthash));
        self::assertFalse($this->objects_contain_hash($remoteobject->contenthash));
    }

    public function test_pusher_get_candidate_objects_wont_get_objects_bigger_than_maximum_filesize() {
        global $DB;
        $object = $this->create_local_object();
        $maximumfilesize = $this->filesystem->get_maximum_upload_filesize() + 1;
        $DB->set_field('files', 'filesize', $maximumfilesize, array('contenthash' => $object->contenthash));

        self::assertFalse($this->objects_contain_hash($object->contenthash));
    }

    public function test_pusher_get_candidate_objects_wont_get_objects_under_size_threshold() {
        global $DB;
        $this->set_pusher_config('sizethreshold', 100);
        $object = $this->create_local_object();
        $DB->set_field('files', 'filesize', 10, array('contenthash' => $object->contenthash));

        self::assertFalse($this->objects_contain_hash($object->contenthash));
    }

    public function test_pusher_get_candidate_objects_wont_get_objects_younger_than_minimum_age() {
        $this->set_pusher_config('minimumage', 100);
        $object = $this->create_local_object();

        self::assertFalse($this->objects_contain_hash($object->contenthash));
    }

    public function test_pusher_can_push_local_file() {
        global $DB;
        $object = $this->create_local_object();

        $this->pusher->execute(array($object));

        $location = $DB->get_field('tool_objectfs_objects', 'location', array('contenthash' => $object->contenthash));
        $this->assertEquals(OBJECT_LOCATION_DUPLICATED, $location);
        $this->assertTrue($this->is_locally_readable_by_hash($object->contenthash));
        $this->assertTrue($this->is_externally_readable_by_hash($object->contenthash));
    }

    public function test_pusher_can_handle_duplicated_file() {
        global $DB;
        $object = $this->create_duplicated_object();

        $this->pusher->execute(array($object));

        $location = $DB->get_field('tool_objectfs_objects', 'location', array('contenthash' => $object->contenthash));
        $this->assertEquals(OBJECT_LOCATION_DUPLICATED, $location);
        $this->assertTrue($this->is_locally_readable_by_hash($object->contenthash));
        $this->assertTrue($this->is_externally_readable_by_hash($object->contenthash));
    }

    public function test_pusher_can_handle_remote_file() {
        global $DB;
        $object = $this->create_remote_object();

        $this->pusher->execute(array($object));

        $location = $DB->get_field('tool_objectfs_objects', 'location', array('contenthash' => $object->contenthash));
        $this->assertEquals(OBJECT_LOCATION_EXTERNAL, $location);
        $this->assertFalse($this->is_locally_readable_by_hash($object->contenthash));
        $this->assertTrue($this->is_externally_readable_by_hash($object->contenthash));
    }

    public function test_pusher_can_push_multiple_objects() {
        global $DB;
        $objects = array();
        for ($i = 0; $i < 5; $i++) {
            $objects[] = $this->create_local_object("Object $i");
        }

        $this->pusher->execute($objects);

        foreach ($objects as $object) {
            $location = $DB->get_field('tool_objectfs_objects', 'location', array('contenthash' => $object->contenthash));
            $this->assertEquals(OBJECT_LOCATION_DUPLICATED, $location);
            $this->assertTrue($this->is_locally_readable_by_hash($object->contenthash));
            $this->assertTrue($this->is_externally_readable_by_hash($object->contenthash));
        }
    }

    public function test_get_candidate_objects_get_one_object_if_files_have_same_hash_different_mimetype() {
        global $DB;
        // Push initial objects so they arnt candidates.
        config::set_config(['filesystem' => get_class($this->filesystem)]);
        $finder = new candidates_finder($this->manipulator, config::instance());
        $objects = $finder->get();
        $this->pusher->execute($objects);

        $object = $this->create_local_object();
        $file = $DB->get_record('files', array('contenthash' => $object->contenthash));

        // Update mimetype to something different and insert as new file.
        $file->mimetype = "differentMimeType";
        $file->pathnamehash = '1234';
        $DB->insert_record('files', $file);

        $objects = $finder->get();

        $this->assertEquals(1, count($objects));
    }

    public function test_get_candidate_objects_get_one_object_if_files_have_same_hash_different_filesize() {
        global $DB;
        // Push initial objects so they arnt candidates.
        config::set_config(['filesystem' => get_class($this->filesystem)]);
        $finder = new candidates_finder($this->manipulator, config::instance());
        $objects = $finder->get();
        $this->pusher->execute($objects);

        $object = $this->create_local_object();
        $file = $DB->get_record('files', array('contenthash' => $object->contenthash));

        // Update filesize to something different and insert as new file.
        $file->filesize = $this->filesystem->get_maximum_upload_filesize() + 100;
        $file->pathnamehash = '1234';
        $DB->insert_record('files', $file);

        $file->filesize = $this->filesystem->get_maximum_upload_filesize() - 100;
        $file->pathnamehash = '12345';
        $DB->insert_record('files', $file);

        $objects = $finder->get();

        $this->assertEquals(1, count($objects));
        $this->assertEquals($this->filesystem->get_maximum_upload_filesize() - 100, reset($objects)->filesize);
        $this->assertEquals($file->contenthash, reset($objects)->contenthash);
    }
}
