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

use tool_objectfs\local\store\object_file_system;

require_once(__DIR__ . '/classes/test_client.php');
require_once(__DIR__ . '/tool_objectfs_testcase.php');

class object_file_system_testcase extends tool_objectfs_testcase {

    public function set_externalclient_config($key, $value) {
        // Get a reflection of externalclient object as a property.
        $reflection = new \ReflectionClass($this->filesystem);
        $externalclientref = $reflection->getParentClass()->getProperty('externalclient');
        $externalclientref->setAccessible(true);

        // Get a reflection of externalclient->$key property.
        $property = new \ReflectionProperty($externalclientref->getValue($this->filesystem), $key);

        // Set new value for externalclient->$key property.
        $property->setValue($externalclientref->getValue($this->filesystem), $value);
    }

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

        $fileperms = substr(sprintf('%04o', fileperms($localpath)), -4);
        $cfgperms = substr(sprintf('%04o', $CFG->filepermissions), -4);
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

    public function test_delete_object_from_local_by_hash_if_can_verify_external_object() {
        $file = $this->create_duplicated_file();
        $contenthash = $file->get_contenthash();
        $externalpath = $this->get_external_path_from_hash($contenthash);
        $localpath = $this->get_local_path_from_storedfile($file);

        $differentfilepath = __DIR__ . '/fixtures/test.txt';
        copy($differentfilepath, $externalpath);

        $location = $this->filesystem->delete_object_from_local_by_hash($contenthash);

        $this->assertEquals(OBJECT_LOCATION_EXTERNAL, $location);
        $this->assertFalse(is_readable($localpath));
        // If grandparent is empty it should be removed.
        $this->assertFalse(is_readable(dirname(dirname($localpath))));
    }

    /**
     * @return array
     */
    public function delete_empty_folders_provider() {
        return [
            [
                /*
                    /filedir/test/d1/file1
                    /filedir/test/d2/file2
                    deleted: 0 dirs
                */
                ['/d1', '/d2'], ['file1', 'file2'], [true, true],  true,
            ],
            [
                /*
                    /filedir/test/d1/file1
                    /filedir/test/d2/
                    deleted: 1dirs
                        - /filedir/test/d2/
                */
                ['/d1', '/d2'], ['file1'], [true, false],  true,
            ],
            [
                /*
                    /filedir/test/d1/
                    /filedir/test/d2/file2
                    deleted: 1 dirs
                        - /filedir/test/d1/
                */
                ['/d1', '/d2'], ['', 'file1'], [false, true],  true,
            ],
            [
                /*
                    /filedir/test/d1/
                    /filedir/test/d2/
                    deleted: 3 dirs
                        - /filedir/test/d1/
                        - /filedir/test/d2/
                        - /filedir/test/
                */
                ['/d1', '/d2'], [], [false, false],  false,
            ],
        ];
    }

    /**
     * @dataProvider delete_empty_folders_provider
     * @param array $dirs Dirs to be created.
     * @param array $files Files to be created.
     * @param array $expectedparentreadable Indicates whether a dir will remain after calling 'delete_empty_folders'.
     * @param bool $expectedgrandparentpathreadable If grandparent dir exists after calling 'delete_empty_folders'.
     */
    public function test_delete_empty_folders(
        $dirs,
        $files,
        $expectedparentreadable,
        $expectedgrandparentpathreadable
    ) {
        global $CFG;
        $testdir = $CFG->dataroot . '/filedir/test';
        foreach ($dirs as $key => $dir) {
            $fullpath = $testdir . $dir;
            mkdir($fullpath, 0777, true);
            $file = !empty($files[$key]) ? $files[$key] : '';
            if ($file !== '') {
                touch($fullpath . '/' . $file);
            }
        }
        $this->filesystem->delete_empty_dirs($testdir);
        foreach ($dirs as $key => $dir) {
             $this->assertEquals($expectedparentreadable[$key], is_readable($testdir . $dir));
        }
        $this->assertEquals($expectedgrandparentpathreadable, is_readable($testdir));
        // Make sure we clean up $testdir after each test case.
        remove_dir($testdir);
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

    public function test_object_storage_deleter_can_delete_object_if_enabledelete_is_on_and_object_is_local() {
        global $CFG;

        $CFG->tool_objectfs_delete_externally = 1;
        $this->filesystem = new test_file_system();
        $file = $this->create_local_file();
        $filehash = $file->get_contenthash();
        $this->delete_draft_files($filehash);
        $this->filesystem->remove_file($filehash);
        $this->assertTrue($this->is_locally_readable_by_hash_in_trashdir($filehash));
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
        $this->assertTrue($this->is_locally_readable_by_hash_in_trashdir($filehash));
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
        $this->assertTrue($this->is_externally_readable_by_hash_in_trashdir($filehash));
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
        $this->assertFalse($this->is_externally_readable_by_hash_in_trashdir($filehash));
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
        $this->assertTrue($this->is_externally_readable_by_hash_in_trashdir($filehash));
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
        $this->assertFalse($this->is_externally_readable_by_hash_in_trashdir($filehash));
    }

    public function test_object_storage_locker_can_acquire_lock_if_object_is_not_locked() {
        $this->filesystem = new test_file_system();
        $file = $this->create_local_file();
        $filehash = $file->get_contenthash();
        $lock = $this->acquire_object_lock($filehash);
        $this->assertEquals(gettype($lock), 'object');
        $this->assertEquals(get_class($lock), 'core\lock\lock');
        $lock->release();
    }

    public function test_can_recover_object_if_deleted_while_local() {
        $this->filesystem = new test_file_system();
        $file = $this->create_local_file();
        $filehash = $file->get_contenthash();
        $this->delete_draft_files($filehash);
        $this->filesystem->remove_file($filehash);
        $this->recover_file($file);
        $this->assertTrue($this->is_locally_readable_by_hash($filehash));
    }

    public function test_can_recover_object_if_deleted_while_duplicated() {
        global $CFG;

        $CFG->tool_objectfs_delete_externally = 1;
        $this->filesystem = new test_file_system();
        $file = $this->create_duplicated_file();
        $filehash = $file->get_contenthash();
        $this->delete_draft_files($filehash);
        $this->filesystem->remove_file($filehash);
        $this->recover_file($file);
        $this->assertTrue($this->is_externally_readable_by_hash($filehash));
    }

    public function test_can_recover_object_if_deleted_while_external() {
        global $CFG;

        $CFG->tool_objectfs_delete_externally = 1;
        $this->filesystem = new test_file_system();
        $file = $this->create_remote_file();
        $filehash = $file->get_contenthash();
        $this->delete_draft_files($filehash);
        $this->filesystem->remove_file($filehash);
        $this->recover_file($file);
        $this->assertTrue($this->is_externally_readable_by_hash($filehash));
    }

    public function test_can_delete_local_file_if_exists_in_trashdir() {
        $this->filesystem = new test_file_system();
        $file = $this->create_local_file();
        $filehash = $file->get_contenthash();
        $this->delete_draft_files($filehash);
        $this->filesystem->remove_file($filehash);
        $this->assertFalse($this->is_locally_readable_by_hash($filehash));

        $file = $this->create_local_file();
        $filehash = $file->get_contenthash();
        $this->delete_draft_files($filehash);
        $this->filesystem->remove_file($filehash);
        $this->assertFalse($this->is_locally_readable_by_hash($filehash));
    }

    public function test_can_generate_signed_url_by_hash_if_object_is_external() {
        $this->filesystem = new test_file_system();
        $file = $this->create_remote_file();
        $filehash = $file->get_contenthash();
        try {
            $signedurl = $this->filesystem->generate_presigned_url_to_external_file($filehash);
            $this->assertTrue($this->is_externally_readable_by_url($signedurl));
        } catch (\coding_exception $e) {
            $this->assertEquals($e->a, 'Pre-signed URLs not supported');
        }
    }

    public function test_presigned_url_configured_method_returns_false_if_not_configured() {
        $this->filesystem = new test_file_system();
        $this->assertFalse($this->filesystem->presigned_url_configured());
    }

    public function test_presigned_url_configured_method_returns_true_if_configured() {
        $this->filesystem = new test_file_system();
        $externalclient = $this->filesystem->get_external_client();

        if (!$externalclient->support_presigned_urls()) {
            $this->markTestSkipped('Pre-signed URLs not supported for given storage.');
        }

        $this->set_externalclient_config('enablepresignedurls', '1');
        $this->assertTrue($this->filesystem->presigned_url_configured());
    }

    public function test_presigned_url_should_redirect_provider() {
        $provider = array();

        // Testing defaults.
        $provider[] = array('Default', 'Default', false);

        // Testing $enablepresignedurls.
        $provider[] = array(1, 'Default', true);
        $provider[] = array('1', 'Default', true);
        $provider[] = array(0, 'Default', false);
        $provider[] = array('0', 'Default', false);
        $provider[] = array('', 'Default', false);
        $provider[] = array(null, 'Default', false);

        // Testing $presignedminfilesize.
        $provider[] = array(1, 0, true);
        $provider[] = array(1, '0', true);
        $provider[] = array(1, '', true);
        $provider[] = array(1, null, false);

        // Testing minimum file size to be greater than file size = 10 (default).
        $provider[] = array(1, 11, false);
        $provider[] = array(1, '11', false);

        // Testing minimum file size to be less than file size = 10 (default).
        $provider[] = array(1, 9, true);
        $provider[] = array(1, '9', true);

        // Testing nulls and empty strings.
        $provider[] = array(null, null, false);
        $provider[] = array(null, '', false);
        $provider[] = array('', null, false);
        $provider[] = array('', '', false);

        return $provider;
    }

    /**
     * @dataProvider test_presigned_url_should_redirect_provider
     *
     * @param $enablepresignedurls mixed enable pre-signed URLs.
     * @param $presignedminfilesize mixed minimum file size to be redirected to pre-signed URL.
     * @param $result boolean expected result.
     */
    public function test_presigned_url_should_redirect_method_with_data_provider($enablepresignedurls, $presignedminfilesize, $result) {
        $this->filesystem = new test_file_system();
        $externalclient = $this->filesystem->get_external_client();

        if (!$externalclient->support_presigned_urls()) {
            $this->markTestSkipped('Pre-signed URLs not supported for given storage.');
        }

        if ($enablepresignedurls !== 'Default') {
            $this->set_externalclient_config('enablepresignedurls', $enablepresignedurls);
        }

        if ($presignedminfilesize !== 'Default') {
            $this->set_externalclient_config('presignedminfilesize', $presignedminfilesize);
        }

        $object = $this->create_local_object();
        $this->assertEquals($result, $this->filesystem->presigned_url_should_redirect($object->contenthash));
    }
}
