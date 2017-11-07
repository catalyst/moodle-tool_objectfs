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

require_once(__DIR__ . '/classes/test_client.php');
require_once(__DIR__ . '/classes/test_file_system.php');

abstract class tool_objectfs_testcase extends \advanced_testcase {

    protected function setUp() {
        global $CFG;
        $CFG->alternative_file_system_class = '\\tool_objectfs\\tests\\test_file_system';
        $this->filesystem = new test_file_system();
        $this->logger = new \tool_objectfs\log\null_logger();
        $this->resetAfterTest(true);
    }

    protected function reset_file_system() {
        $this->filesystem = new test_file_system();
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
        // Above method does not set a file size, we do this it has a positive filesize.
        $DB->set_field('files', 'filesize', 10, array('contenthash' => $file->get_contenthash()));

        update_object_record($file->get_contenthash(), OBJECT_LOCATION_LOCAL);

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
            'filename'  => md5($content), // Unqiue content should guarentee unique path.
            'source'    => $sourcefield,
        );
        $file = $fs->create_file_from_string($filerecord, $content);
        // Above method does not set a file size, we do this it has a positive filesize.
        $DB->set_field('files', 'filesize', 10, array('contenthash' => $file->get_contenthash()));

        update_object_record($file->get_contenthash(), OBJECT_LOCATION_LOCAL);
        return $file;
    }

    protected function create_duplicated_file($content = 'test content') {
        $file = $this->create_local_file($content);
        $contenthash = $file->get_contenthash();
        $this->filesystem->copy_object_from_local_to_external_by_hash($contenthash);
        update_object_record($contenthash, OBJECT_LOCATION_DUPLICATED);
        return $file;
    }

    protected function create_remote_file($content = 'test content') {
        $file = $this->create_duplicated_file($content);
        $contenthash = $file->get_contenthash();
        $this->filesystem->delete_object_from_local_by_hash($contenthash);
        update_object_record($contenthash, OBJECT_LOCATION_EXTERNAL);
        return $file;
    }

    protected function create_error_file() {
        $file = $this->create_local_file();
        $path = $this->get_local_path_from_storedfile($file);
        unlink($path);
        update_object_record($file->get_contenthash(), OBJECT_LOCATION_ERROR);
        return $file;
    }

    protected function get_external_path_from_hash($contenthash) {
        $reflection = new \ReflectionMethod(object_file_system::class, 'get_external_path_from_hash');
        $reflection->setAccessible(true);
        return $reflection->invokeArgs($this->filesystem, [$contenthash]);
    }

    protected function get_external_path_from_storedfile($file) {
        $contenthash = $file->get_contenthash();
        return $this->get_external_path_from_hash($contenthash);
    }

    // We want acces to local path for testing so we use a reflection method as opposed to rewriting here.
    protected function get_local_path_from_hash($contenthash) {
        $reflection = new \ReflectionMethod(object_file_system::class, 'get_local_path_from_hash');
        $reflection->setAccessible(true);
        return $reflection->invokeArgs($this->filesystem, [$contenthash]);
    }

    protected function get_local_path_from_storedfile($file) {
        $contenthash = $file->get_contenthash();
        return $this->get_local_path_from_hash($contenthash);
    }

    protected function create_local_object($content = 'local object content') {
        $file = $this->create_local_file($content);
        $objectrecord = new \stdClass();
        $objectrecord->contenthash = $file->get_contenthash();
        $objectrecord->location = OBJECT_LOCATION_LOCAL;
        $objectrecord->filesize = $file->get_filesize();
        return $objectrecord;
    }

    protected function create_duplicated_object($content = 'duplicated object content') {
        $file = $this->create_duplicated_file($content);
        $objectrecord = new \stdClass();
        $objectrecord->contenthash = $file->get_contenthash();
        $objectrecord->location = OBJECT_LOCATION_DUPLICATED;
        $objectrecord->filesize = $file->get_filesize();
        return $objectrecord;
    }

    protected function create_remote_object($content = 'remote object content') {
        $file = $this->create_remote_file($content);
        $objectrecord = new \stdClass();
        $objectrecord->contenthash = $file->get_contenthash();
        $objectrecord->location = OBJECT_LOCATION_EXTERNAL;
        $objectrecord->filesize = $file->get_filesize();
        return $objectrecord;
    }

    protected function create_error_object($content = 'error object content') {
        $file = $this->create_error_file($content);
        $objectrecord = new \stdClass();
        $objectrecord->contenthash = $file->get_contenthash();
        $objectrecord->location = OBJECT_LOCATION_ERROR;
        $objectrecord->filesize = $file->get_filesize();
        return $objectrecord;
    }

    protected function is_locally_readable_by_hash($contenthash) {
        $localpath = $this->get_local_path_from_hash($contenthash);
        return is_readable($localpath);
    }

    protected function is_externally_readable_by_hash($contenthash) {
        $externalpath = $this->get_external_path_from_hash($contenthash);
        return is_readable($externalpath);
    }
}

