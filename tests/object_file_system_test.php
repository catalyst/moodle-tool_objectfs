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
require_once(__DIR__ . '/tool_objectfs_testcase.php');

class object_file_system_testcase extends tool_objectfs_testcase {

    public function test_get_remote_path_from_storedfile_returns_local_path_if_local() {
        $file = $this->create_local_file();
        $expectedpath = $this->get_local_path_from_storedfile($file);

        $reflection = new \ReflectionMethod(object_file_system::class, 'get_remote_path_from_storedfile');
        $reflection->setAccessible(true);
        $actualpath = $reflection->invokeArgs($this->filesystem, [$file]);

        $this->assertEquals($expectedpath, $actualpath);
    }

    public function test_get_remote_path_from_storedfile_returns_external_path_if_not_local() {
        $file = $this->create_remote_file();
        $expectedpath = $this->get_external_path_from_storedfile($file);

        $reflection = new \ReflectionMethod(object_file_system::class, 'get_remote_path_from_storedfile');
        $reflection->setAccessible(true);
        $actualpath = $reflection->invokeArgs($this->filesystem, [$file]);

        $this->assertEquals($expectedpath, $actualpath);
    }

    public function test_get_remote_path_from_storedfile_returns_external_path_if_duplicated_and_preferexternal() {
        set_config('preferexternal', true, 'tool_objectfs');
        $this->reset_file_system(); // Needed to load new config.
        $file = $this->create_duplicated_file();
        $expectedpath = $this->get_external_path_from_storedfile($file);

        $reflection = new \ReflectionMethod(object_file_system::class, 'get_remote_path_from_storedfile');
        $reflection->setAccessible(true);
        $actualpath = $reflection->invokeArgs($this->filesystem, [$file]);

        $this->assertEquals($expectedpath, $actualpath);
    }

    public function test_get_local_path_from_hash_will_fetch_remote_if_fetchifnotfound() {
        $file = $this->create_remote_file();
        $filehash = $file->get_contenthash();
        $expectedpath = $this->get_local_path_from_hash($filehash);

        $reflection = new \ReflectionMethod(object_file_system::class, 'get_local_path_from_hash');
        $reflection->setAccessible(true);
        $actualpath = $reflection->invokeArgs($this->filesystem, [$filehash, true]);

        $this->assertEquals($expectedpath, $actualpath);
        $this->assertTrue(is_readable($actualpath));
    }

    public function test_get_local_path_from_hash_fetch_remote_will_restore_file_permissions() {
        global $CFG;
        $file = $this->create_remote_file();
        $filehash = $file->get_contenthash();

        $reflection = new \ReflectionMethod(object_file_system::class, 'get_local_path_from_hash');
        $reflection->setAccessible(true);
        $localpath = $reflection->invokeArgs($this->filesystem, [$filehash, true]);

        $fileperms = substr(sprintf('%o', fileperms($localpath)), -4);
        $cfgperms = substr(sprintf('%o', $CFG->filepermissions), -4);
        $this->assertEquals($cfgperms, $fileperms);
    }

    public function test_copy_object_from_external_to_local() {
        $file = $this->create_remote_file();
        $filehash = $file->get_contenthash();
        $localpath = $this->get_local_path_from_storedfile($file);

        $location = $this->filesystem->copy_object_from_external_to_local_by_hash($filehash);

        $this->assertEquals(OBJECT_LOCATION_DUPLICATED, $location);
        $this->assertTrue(is_readable($localpath));
    }

    public function test_copy_object_from_external_to_local_by_hash_if_local() {
        $file = $this->create_local_file();
        $filehash = $file->get_contenthash();

        $location = $this->filesystem->copy_object_from_external_to_local_by_hash($filehash);

        $this->assertEquals(OBJECT_LOCATION_LOCAL, $location);
    }

    public function test_copy_object_from_external_to_local_by_hash_succeeds_if_already_duplicated() {
        $file = $this->create_duplicated_file();
        $filehash = $file->get_contenthash();

        $location = $this->filesystem->copy_object_from_external_to_local_by_hash($filehash);

        $this->assertEquals(OBJECT_LOCATION_DUPLICATED, $location);
    }

    public function test_copy_object_from_external_to_local_by_hash_if_not_local_and_not_remote() {
        $fakehash = 'this is a fake hash';

        $location = $this->filesystem->copy_object_from_external_to_local_by_hash($fakehash);

        $this->assertEquals(OBJECT_LOCATION_ERROR, $location);
    }

    public function test_copy_object_from_local_to_external_by_hash() {
        $file = $this->create_local_file();
        $filehash = $file->get_contenthash();
        $externalpath = $this->get_external_path_from_storedfile($file);

        $location = $this->filesystem->copy_object_from_local_to_external_by_hash($filehash);

        $this->assertEquals(OBJECT_LOCATION_DUPLICATED, $location);
        $this->assertTrue(is_readable($externalpath));
    }

    public function test_copy_object_from_local_to_external_by_hash_if_remote() {
        $file = $this->create_remote_file();
        $filehash = $file->get_contenthash();

        $location = $this->filesystem->copy_object_from_local_to_external_by_hash($filehash);

        $this->assertEquals(OBJECT_LOCATION_EXTERNAL, $location);
    }

    public function test_copy_object_from_local_to_external_by_hash_succeeds_if_already_duplicated() {
        $file = $this->create_duplicated_file();
        $filehash = $file->get_contenthash();

        $location = $this->filesystem->copy_object_from_local_to_external_by_hash($filehash);

        $this->assertEquals(OBJECT_LOCATION_DUPLICATED, $location);
    }

    public function test_copy_object_from_local_to_external_by_hash_if_not_local_and_not_remote() {
        $fakehash = 'this is a fake hash';

        $location = $this->filesystem->copy_object_from_local_to_external_by_hash($fakehash);

        $this->assertEquals(OBJECT_LOCATION_ERROR, $location);
    }

    public function test_delete_object_from_local_by_hash() {
        $file = $this->create_duplicated_file();
        $filehash = $file->get_contenthash();
        $localpath = $this->get_local_path_from_storedfile($file);

        $location = $this->filesystem->delete_object_from_local_by_hash($filehash);

        $this->assertEquals(OBJECT_LOCATION_EXTERNAL, $location);
        $this->assertFalse(is_readable($localpath));
    }

    public function test_delete_object_from_local_by_hash_if_not_remote() {
        $file = $this->create_local_file();
        $filehash = $file->get_contenthash();
        $localpath = $this->get_local_path_from_storedfile($file);

        $location = $this->filesystem->delete_object_from_local_by_hash($filehash);

        $this->assertEquals(OBJECT_LOCATION_LOCAL, $location);
        $this->assertTrue(is_readable($localpath));
    }

    public function test_delete_object_from_local_by_hash_if_not_local() {
        $fakehash = 'this is a fake hash';

        $location = $this->filesystem->delete_object_from_local_by_hash($fakehash);

        $this->assertEquals(OBJECT_LOCATION_ERROR, $location);
    }

    public function test_delete_object_from_local_by_hash_if_cant_verify_external_object() {
        $file = $this->create_duplicated_file();
        $externalpath = $this->get_external_path_from_hash($file->get_contenthash());
        $localpath = $this->get_local_path_from_storedfile($file);

        $differentfilepath = __DIR__ . '/fixtures/test.txt';
        copy($differentfilepath, $externalpath);

        $location = $this->filesystem->delete_object_from_local_by_hash($file->get_contenthash());

        $this->assertEquals(OBJECT_LOCATION_DUPLICATED, $location);
        $this->assertTrue(is_readable($localpath));
    }

    public function test_readfile_if_object_is_local() {
        $expectedcontent = 'expected content';
        $file = $this->create_local_file($expectedcontent);

        $this->expectOutputString($expectedcontent);

        $this->filesystem->readfile($file);
    }

    public function test_readfile_if_object_is_remote() {
        $expectedcontent = 'expected content';
        $file = $this->create_remote_file($expectedcontent);

        $this->expectOutputString($expectedcontent);

        $this->filesystem->readfile($file);
    }

    public function test_readfile_updates_object_with_error_location_on_fail() {
        global $DB;
        $fakefile = $this->create_error_file();

        // Phpunit will fail if PHP warning is thrown (which we want)
        // so we surpress here.
        set_error_handler(array($this, 'test_error_surpressor'));
        $this->filesystem->readfile($fakefile);
        restore_error_handler();

        $location = $DB->get_field('tool_objectfs_objects', 'location', array('contenthash' => $fakefile->get_contenthash()));
        $this->assertEquals(OBJECT_LOCATION_ERROR, $location);
    }

    public function test_get_content_if_object_is_local() {
        $expectedcontent = 'expected content';
        $file = $this->create_local_file($expectedcontent);

        $actualcontent = $this->filesystem->get_content($file);

        $this->assertEquals($expectedcontent, $actualcontent);
    }

    public function test_get_content_if_object_is_remote() {
        $expectedcontent = 'expected content';
        $file = $this->create_remote_file($expectedcontent);

        $actualcontent = $this->filesystem->get_content($file);

        $this->assertEquals($expectedcontent, $actualcontent);
    }

    public function test_get_content_updates_object_with_error_location_on_fail() {
        global $DB;
        $fakefile = $this->create_error_file();

        // Phpunit will fail if PHP warning is thrown (which we want)
        // so we surpress here.
        set_error_handler(array($this, 'test_error_surpressor'));
        $this->filesystem->get_content($fakefile);
        restore_error_handler();

        $location = $DB->get_field('tool_objectfs_objects', 'location', array('contenthash' => $fakefile->get_contenthash()));
        $this->assertEquals(OBJECT_LOCATION_ERROR, $location);
    }

    public function test_error_surpressor() {
        // We do nothing. We cant surpess warnings
        // normally because phpunit will still fail.
    }

    public function test_xsendfile_updates_object_with_error_location_on_fail() {
        global $DB;
        $fakefile = $this->create_error_file();

        // Phpunit will fail if PHP warning is thrown (which we want)
        // so we surpress here.
        set_error_handler(array($this, 'test_error_surpressor'));
        $this->filesystem->xsendfile($fakefile->get_contenthash());
        restore_error_handler();

        $location = $DB->get_field('tool_objectfs_objects', 'location', array('contenthash' => $fakefile->get_contenthash()));
        $this->assertEquals(OBJECT_LOCATION_ERROR, $location);
    }

    public function test_get_content_file_handle_if_object_is_local() {
        $file = $this->create_local_file();

        $filehandle = $this->filesystem->get_content_file_handle($file);

        $this->assertTrue(is_resource($filehandle));
    }

    public function test_get_content_file_handle_if_object_is_remote() {
        $file = $this->create_remote_file();

        $filehandle = $this->filesystem->get_content_file_handle($file);

        $this->assertTrue(is_resource($filehandle));
    }

    public function test_get_content_file_handle_will_pull_remote_object_if_gzopen() {
        $file = $this->create_remote_file();
        $localpath = $this->get_local_path_from_storedfile($file);

        $filehandle = $this->filesystem->get_content_file_handle($file, \stored_file::FILE_HANDLE_GZOPEN);

        $this->assertTrue(is_resource($filehandle));
        $this->assertTrue(is_readable($localpath));
    }

    public function test_get_content_file_handle_updates_object_with_error_location_on_fail() {
        global $DB;
        $fakefile = $this->create_error_file();

        // Phpunit will fail if PHP warning is thrown (which we want)
        // so we surpress here.
        set_error_handler(array($this, 'test_error_surpressor'));
        $filehandle = $this->filesystem->get_content_file_handle($fakefile);
        restore_error_handler();

        $location = $DB->get_field('tool_objectfs_objects', 'location', array('contenthash' => $fakefile->get_contenthash()));
        $this->assertEquals(OBJECT_LOCATION_ERROR, $location);
    }

    public function test_remove_file_will_remove_local_file() {
        global $DB;
        $file = $this->create_local_file();
        $filehash = $file->get_contenthash();

        // Delete file record so remove file will remove.
        $DB->delete_records('files', array('contenthash' => $filehash));
        $this->filesystem->remove_file($filehash);

        $islocalreadable = $this->filesystem->is_file_readable_locally_by_hash($filehash);
        $this->assertFalse($islocalreadable);
    }

    public function test_remove_file_will_not_remove_remote_file() {
        global $DB;
        $file = $this->create_remote_file();
        $filehash = $file->get_contenthash();

        // Delete file record so remove file will remove.
        $DB->delete_records('files', array('contenthash' => $filehash));
        $this->filesystem->remove_file($filehash);

        $isremotereadable = $this->is_externally_readable_by_hash($filehash);
        $this->assertTrue($isremotereadable);
    }

    public function test_file_is_seekable() {
        $file = $this->create_remote_file('this is some content for the remote file');
        $filehandle = $this->filesystem->get_content_file_handle($file);

        $metadata = stream_get_meta_data($filehandle);

        $this->assertTrue($metadata['seekable']);
        $this->assertEquals(0, fseek($filehandle, 10, SEEK_SET));
        $this->assertTrue(rewind($filehandle));
    }

    protected function delete_draft_files($contenthash) {
        global $DB;

        $DB->delete_records('files', array('contenthash' => $contenthash));
    }

    public function test_object_storage_deleter_can_delete_object_if_enabledelete_is_on_and_object_is_local() {
        global $CFG;

        $CFG->tool_objectfs_delete_externally = 1;
        $this->filesystem = new test_file_system();
        $file = $this->create_local_file();
        $filehash = $file->get_contenthash();
        $this->delete_draft_files($filehash);
        $this->filesystem->remove_file($filehash);
        $this->assertFalse($this->is_locally_readable_by_hash($filehash));
        $this->assertFalse($this->is_externally_readable_by_hash($filehash));
    }

    public function test_object_storage_deleter_can_delete_object_if_enabledelete_is_off_and_object_is_local() {
        global $CFG;

        $CFG->tool_objectfs_delete_externally = 0;
        $this->filesystem = new test_file_system();
        $file = $this->create_local_file();
        $filehash = $file->get_contenthash();
        $this->delete_draft_files($filehash);
        $this->filesystem->remove_file($filehash);
        $this->assertFalse($this->is_locally_readable_by_hash($filehash));
        $this->assertFalse($this->is_externally_readable_by_hash($filehash));
    }

    public function test_object_storage_deleter_can_delete_object_if_enabledelete_is_on_and_object_is_duplicated() {
        global $CFG;

        $CFG->tool_objectfs_delete_externally = 1;
        $this->filesystem = new test_file_system();
        $file = $this->create_duplicated_file();
        $filehash = $file->get_contenthash();
        $this->delete_draft_files($filehash);
        $this->filesystem->remove_file($filehash);
        $this->assertFalse($this->is_locally_readable_by_hash($filehash));
        $this->assertFalse($this->is_externally_readable_by_hash($filehash));
    }

    public function test_object_storage_deleter_can_delete_object_if_enabledelete_is_off_and_object_is_duplicated() {
        global $CFG;

        $CFG->tool_objectfs_delete_externally = 0;
        $this->filesystem = new test_file_system();
        $file = $this->create_duplicated_file();
        $filehash = $file->get_contenthash();
        $this->delete_draft_files($filehash);
        $this->filesystem->remove_file($filehash);
        $this->assertFalse($this->is_locally_readable_by_hash($filehash));
        $this->assertTrue($this->is_externally_readable_by_hash($filehash));
    }

    public function test_object_storage_deleter_can_delete_object_if_enabledelete_is_on_and_object_is_remote() {
        global $CFG;

        $CFG->tool_objectfs_delete_externally = 1;
        $this->filesystem = new test_file_system();
        $file = $this->create_remote_file();
        $filehash = $file->get_contenthash();
        $this->delete_draft_files($filehash);
        $this->filesystem->remove_file($filehash);
        $this->assertFalse($this->is_locally_readable_by_hash($filehash));
        $this->assertFalse($this->is_externally_readable_by_hash($filehash));
    }

    public function test_object_storage_deleter_can_delete_object_if_enabledelete_is_off_and_object_is_remote() {
        global $CFG;

        $CFG->tool_objectfs_delete_externally = 0;
        $this->filesystem = new test_file_system();
        $file = $this->create_remote_file();
        $filehash = $file->get_contenthash();
        $this->delete_draft_files($filehash);
        $this->filesystem->remove_file($filehash);
        $this->assertFalse($this->is_locally_readable_by_hash($filehash));
        $this->assertTrue($this->is_externally_readable_by_hash($filehash));
    }

}

