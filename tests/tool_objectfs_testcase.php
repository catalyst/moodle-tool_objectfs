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

require_once(__DIR__ . '/test_client.php');

abstract class tool_objectfs_testcase extends \advanced_testcase {

    protected function setUp() {
        global $CFG;
        $CFG->objectfs_remote_client_class = '\tool_objectfs_test_client';
        $this->filesystem = new object_file_system();
        $this->resetAfterTest(true);
    }

    protected function reset_file_system() {
        $this->filesystem = new object_file_system();
    }

    protected function create_local_file_from_path($pathname) {
        global $DB;
        $fs = get_file_storage();
        $syscontext = \context_system::instance();
        $component = 'core';
        $filearea  = 'unittest';
        $itemid    = 0;
        $filepath  = '/';
        $sourcefield = 'Copyright stuff';
        $filerecord = array(
            'contextid' => $syscontext->id,
            'component' => $component,
            'filearea'  => $filearea,
            'itemid'    => $itemid,
            'filepath'  => $filepath,
            'filename'  => $pathname,
            'source'    => $sourcefield,
        );
        $file = $fs->create_file_from_pathname($filerecord, $pathname);
        return $file;
    }

    protected function create_local_file($content = 'test content') {
        global $DB;
        $fs = get_file_storage();
        $syscontext = \context_system::instance();
        $component = 'core';
        $filearea  = 'unittest';
        $itemid    = 0;
        $filepath  = '/';
        $sourcefield = 'Copyright stuff';
        $filerecord = array(
            'contextid' => $syscontext->id,
            'component' => $component,
            'filearea'  => $filearea,
            'itemid'    => $itemid,
            'filepath'  => $filepath,
            'filename'  => 'testfile',
            'source'    => $sourcefield,
        );
        $file = $fs->create_file_from_string($filerecord, $content);

        log_object_location($file->get_contenthash(), OBJECT_LOCATION_LOCAL);
        return $file;
    }

    protected function create_duplicated_file($content = 'test content') {
        $file = $this->create_local_file($content);
        $contenthash = $file->get_contenthash();
        $this->filesystem->copy_object_from_local_to_remote_by_hash($contenthash);
        log_object_location($contenthash, OBJECT_LOCATION_DUPLICATED);
        return $file;
    }

    protected function create_remote_file($content = 'test content') {
        $file = $this->create_duplicated_file($content);
        $contenthash = $file->get_contenthash();
        $this->filesystem->delete_object_from_local_by_hash($contenthash);
        log_object_location($contenthash, OBJECT_LOCATION_REMOTE);
        return $file;
    }

    protected function get_remote_path_from_hash($contenthash) {
        global $CFG;
        $config = get_objectfs_config();
        $remoteclientclass = $CFG->objectfs_remote_client_class;
        $client = new $remoteclientclass($config);
        return $client->get_object_fullpath_from_hash($contenthash);
    }

    protected function get_remote_path_from_storedfile($file) {
        $contenthash = $file->get_contenthash();
        return $this->get_remote_path_from_hash($contenthash);
    }

    protected function get_local_path_from_hash($contenthash) {
        $reflection = new \ReflectionMethod(object_file_system::class, 'get_local_path_from_hash');
        $reflection->setAccessible(true);
        return $reflection->invokeArgs($this->filesystem, [$contenthash]);
    }

    protected function get_local_path_from_storedfile($file) {
        $contenthash = $file->get_contenthash();
        return $this->get_local_path_from_hash($contenthash);
    }
}

