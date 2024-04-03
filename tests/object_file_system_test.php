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

namespace tool_objectfs;

use tool_objectfs\local\store\object_file_system;
use tool_objectfs\local\manager;
use tool_objectfs\tests\test_file_system;

/**
 * Test basic operations of object file system.
 *
 * @covers \tool_objectfs\local\store\object_file_system
 * @package tool_objectfs
 */
class object_file_system_test extends tests\testcase {

    /**
     * set_externalclient_config
     * @param mixed $key
     * @param mixed $value
     *
     * @return void
     */
    public function set_externalclient_config($key, $value) {
        // Get a reflection of externalclient object as a property.
        $reflection = new \ReflectionClass($this->filesystem);
        $externalclientref = $reflection->getParentClass()->getProperty('externalclient');
        $externalclientref->setAccessible(true);

        // Get a reflection of externalclient->$key property.
        $property = new \ReflectionProperty($externalclientref->getValue($this->filesystem), $key);
        $property->setAccessible(true);

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
    }

    /**
     * delete_empty_folders_provider
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
     * test_delete_empty_folders_provider
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

        // Totara 12 clean-up.
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
        set_error_handler(array($this, 'error_surpressor'));
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
        set_error_handler(array($this, 'error_surpressor'));
        $this->filesystem->get_content($fakefile);
        restore_error_handler();

        $location = $DB->get_field('tool_objectfs_objects', 'location', array('contenthash' => $fakefile->get_contenthash()));
        $this->assertEquals(OBJECT_LOCATION_ERROR, $location);
    }

    /**
     * error_surpressor
     * @return void
     */
    public function error_surpressor() {
        // We do nothing. We cant surpess warnings
        // normally because phpunit will still fail.
    }

    public function test_xsendfile_updates_object_with_error_location_on_fail() {
        global $DB;
        $fakefile = $this->create_error_file();

        // Phpunit will fail if PHP warning is thrown (which we want)
        // so we surpress here.
        set_error_handler(array($this, 'error_surpressor'));
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
        set_error_handler(array($this, 'error_surpressor'));
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

        $CFG->forced_plugin_settings['tool_objectfs']['deleteexternal'] = TOOL_OBJECTFS_DELETE_EXTERNAL_FULL;
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

        $CFG->forced_plugin_settings['tool_objectfs']['deleteexternal'] = false;
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

        $CFG->forced_plugin_settings['tool_objectfs']['deleteexternal'] = TOOL_OBJECTFS_DELETE_EXTERNAL_FULL;
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

        $CFG->forced_plugin_settings['tool_objectfs']['deleteexternal'] = false;
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

        $CFG->forced_plugin_settings['tool_objectfs']['deleteexternal'] = TOOL_OBJECTFS_DELETE_EXTERNAL_FULL;
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

        $CFG->forced_plugin_settings['tool_objectfs']['deleteexternal'] = false;
        $this->filesystem = new test_file_system();
        $file = $this->create_remote_file();
        $filehash = $file->get_contenthash();
        $this->delete_draft_files($filehash);
        $this->filesystem->remove_file($filehash);
        $this->assertFalse($this->is_locally_readable_by_hash($filehash));
        $this->assertTrue($this->is_externally_readable_by_hash($filehash));
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

    public function test_can_recover_object_if_deleted_while_duplicated() {
        global $CFG;

        $CFG->forced_plugin_settings['tool_objectfs']['deleteexternal'] = TOOL_OBJECTFS_DELETE_EXTERNAL_TRASH;
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

        $CFG->forced_plugin_settings['tool_objectfs']['deleteexternal'] = TOOL_OBJECTFS_DELETE_EXTERNAL_TRASH;
        $this->filesystem = new test_file_system();
        $file = $this->create_remote_file();
        $filehash = $file->get_contenthash();
        $this->delete_draft_files($filehash);
        $this->filesystem->remove_file($filehash);
        $this->recover_file($file);
        $this->assertTrue($this->is_externally_readable_by_hash($filehash));
    }

    public function test_can_generate_signed_url_by_hash_if_object_is_external() {
        $this->filesystem = new test_file_system();
        $file = $this->create_remote_file();
        $filehash = $file->get_contenthash();
        try {
            $signedurl = $this->filesystem->externalclient->generate_presigned_url($filehash);
            $this->assertTrue($this->is_externally_readable_by_url($signedurl));
        } catch (\coding_exception $e) {
            $this->assertEquals($e->a, 'Pre-signed URLs not supported');
        }
    }

    public function test_can_generate_signed_url_with_headers() {
        $this->filesystem = new test_file_system();
        $file = $this->create_remote_file();
        $filehash = $file->get_contenthash();
        try {
            $headers = [
                'Content-Disposition' => 'attachment; filename="filename.txt"',
                'Content-Type' => 'text/plain',
            ];
            $signedurl = $this->filesystem->externalclient->generate_presigned_url($filehash, $headers);
            $this->assertTrue($this->is_externally_readable_by_url($signedurl));
        } catch (\coding_exception $e) {
            $this->assertEquals($e->a, 'Pre-signed URLs not supported');
        }
    }

    public function test_can_generate_signed_url_with_unicode_filename() {
        $this->filesystem = new test_file_system();
        $file = $this->create_remote_file();
        $filehash = $file->get_contenthash();
        try {
            $headers = [
                    'Content-Disposition' => 'attachment; filename="😀.txt"',
                    'Content-Type' => 'text/plain',
            ];
            $signedurl = $this->filesystem->externalclient->generate_presigned_url($filehash, $headers);
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

    /**
     * presigned_url_should_redirect_provider
     * @return array
     */
    public function presigned_url_should_redirect_provider() {
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

        // Testing minimum file size to be greater than file size.
        // 12 is a size of the file with 'test content' content.
        $provider[] = array(1, 13, false);
        $provider[] = array(1, '13', false);

        // Testing minimum file size to be less than file size.
        // 12 is a size of the file with 'test content' content.
        $provider[] = array(1, 11, true);
        $provider[] = array(1, '11', true);

        // Testing nulls and empty strings.
        $provider[] = array(null, null, false);
        $provider[] = array(null, '', false);
        $provider[] = array('', null, false);
        $provider[] = array('', '', false);

        return $provider;
    }

    /**
     * test_presigned_url_should_redirect_provider
     *
     * @param mixed $enablepresignedurls enable pre-signed URLs.
     * @param mixed $presignedminfilesize minimum file size to be redirected to pre-signed URL.
     * @param bool $result expected result.
     * @throws \dml_exception
     */
    public function test_presigned_url_should_redirect_method_with_data_provider($enablepresignedurls,
            $presignedminfilesize, $result) {
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

        if ($this->filesystem->presigned_url_configured()) {
            $file = $this->create_local_file('test content');
            set_config('signingwhitelist', '*', 'tool_objectfs');
            $this->assertEquals($result, $this->filesystem->presigned_url_should_redirect($file->get_contenthash()));
            $this->assertEquals($result, $this->filesystem->presigned_url_should_redirect_file($file));
        } else {
            $this->assertEquals($result, false);
        }
    }

    /**
     * Data provider for test_get_expiration_time_method_if_supported().
     *
     * @return array
     */
    public function get_expiration_time_method_if_supported_provider() {
        $now = time();

        // Seconds after the minute from X.
        $secondsafternow = ($now % MINSECS);
        $secondsafternowsub100 = $secondsafternow;
        $secondsafternowadd30 = $secondsafternow;
        $secondsafternowadd100 = $secondsafternow;
        $secondsafternowaddweek = ($now + WEEKSECS) % MINSECS;

        return [
            // Default Pre-Signed URL expiration time and int-like 'Expires' header.
            [7200, $now, 0, $now + 7200 + MINSECS - $secondsafternow],
            [7200, $now, $now - 100, $now + (2 * MINSECS) - $secondsafternowsub100],
            [7200, $now, $now + 30, $now + (2 * MINSECS) - $secondsafternowadd30],
            [7200, $now, $now + 100, $now + (2 * MINSECS) - $secondsafternowadd100],
            [7200, $now, $now + WEEKSECS + HOURSECS, $now + WEEKSECS - MINSECS - $secondsafternowaddweek],

            // Default Pre-Signed URL expiration time and string-like 'Expires' header.
            [7200, $now, 'Thu, 01 Jan 1970 00:00:00 GMT', $now + 7200 + MINSECS - $secondsafternow],
            [7200, $now, userdate($now - 100, '%a, %d %b %Y %H:%M:%S'), $now + (2 * MINSECS) - $secondsafternowsub100],
            [7200, $now, userdate($now + 30, '%a, %d %b %Y %H:%M:%S'), $now + (2 * MINSECS) - $secondsafternowadd30],
            [7200, $now, userdate($now + 100, '%a, %d %b %Y %H:%M:%S'), $now + (2 * MINSECS) - $secondsafternowadd100],
            [7200, $now, userdate($now + WEEKSECS + HOURSECS, '%a, %d %b %Y %H:%M:%S'),
            $now + WEEKSECS - MINSECS - $secondsafternowaddweek],

            // Custom Pre-Signed URL expiration time and int-like 'Expires' header.
            [0, $now, 0, $now + (2 * MINSECS) - $secondsafternow],
            [600, $now, 0, $now + 600 + MINSECS - $secondsafternow],
            [600, $now, $now - 100, $now + (2 * MINSECS) - $secondsafternowsub100],
            [600, $now, $now + 30, $now + (2 * MINSECS) - $secondsafternowadd30],
            [600, $now, $now + 100, $now + (2 * MINSECS) - $secondsafternowadd100],
            [600, $now, $now + WEEKSECS + HOURSECS, $now + WEEKSECS - MINSECS - $secondsafternowaddweek],

            // Custom Pre-Signed URL expiration time and string-like 'Expires' header.
            [0, $now, 'Thu, 01 Jan 1970 00:00:00 GMT', $now + (2 * MINSECS) - $secondsafternow],
            [600, $now, 'Thu, 01 Jan 1970 00:00:00 GMT', $now + 600 + MINSECS - $secondsafternow],
            [600, $now, userdate($now - 100, '%a, %d %b %Y %H:%M:%S'), $now + (2 * MINSECS) - $secondsafternowsub100],
            [600, $now, userdate($now + 30, '%a, %d %b %Y %H:%M:%S'), $now + (2 * MINSECS) - $secondsafternowadd30],
            [600, $now, userdate($now + 100, '%a, %d %b %Y %H:%M:%S'), $now + (2 * MINSECS) - $secondsafternowadd100],
            [600, $now, userdate($now + WEEKSECS + HOURSECS, '%a, %d %b %Y %H:%M:%S'),
            $now + WEEKSECS - MINSECS - $secondsafternowaddweek],
        ];
    }

    /**
     * Test S3 and DO clients get_expiration_time() method.
     * Available when running integration tests.
     *
     * @dataProvider get_expiration_time_method_if_supported_provider
     *
     * @param int   $expirationsetting Pre-Signed URL expiration time
     * @param int   $now               Now timestamp
     * @param mixed $expiresheader     'Expires' header
     * @param int   $expectedresult    Expiration timestamp for URL
     */
    public function test_get_expiration_time_method_if_supported($expirationsetting, $now, $expiresheader, $expectedresult) {
        $this->filesystem = new test_file_system();
        $externalclient = $this->filesystem->get_external_client();

        if (!$externalclient->support_presigned_urls()) {
            $this->markTestSkipped('Pre-signed URLs not supported for given storage.');
        }

        if ($expirationsetting !== 7200) {
            $this->set_externalclient_config('expirationtime', $expirationsetting);
        }

        $this->assertEquals($expectedresult, $externalclient->get_expiration_time($now, $expiresheader));
    }

    /**
     * Test copy_content_from_storedfile() method does direct copying.
     */
    public function test_copy_content_from_storedfile() {
        $file = $this->create_remote_file();
        // Confirm, that the file is not readable locally.
        $this->assertFalse($this->filesystem->is_file_readable_locally_by_storedfile($file));
        // Get the current remote path.
        $currentpath = $this->filesystem->get_remote_path_from_storedfile($file);
        // Copy the file to new external path.
        $result = $this->filesystem->copy_content_from_storedfile($file, $currentpath . '_new');
        // Confirm the file copied successfully and method returns true.
        $this->assertTrue($result);
        // Confirm, that the file wasn't downloaded locally and was copied directly to new path.
        $this->assertFalse($this->filesystem->is_file_readable_locally_by_storedfile($file));
    }

    /**
     * Test get_filesize_by_contenthash() returns file size by its contenthash.
     */
    public function test_get_filesize_by_contenthash() {
        // Test existing file.
        $file = $this->create_local_file();
        $actual = $this->filesystem->get_filesize_by_contenthash($file->get_contenthash());
        $this->assertEquals($file->get_filesize(), $actual);
        // Test missing file.
        $fakehash = 'this is a fake hash';
        $actual = $this->filesystem->get_filesize_by_contenthash($fakehash);
        $this->assertFalse($actual);
    }

    /**
     * Data provider for test_get_valid_http_ranges().
     *
     * @return array
     */
    public function get_valid_http_ranges_provider() {
        return [
            ['', 0, false],
            ['bytes=0-', 100, (object)['rangefrom' => 0, 'rangeto' => 99, 'length' => 100]],
            ['bytes=0-49/100', 100, (object)['rangefrom' => 0, 'rangeto' => 49, 'length' => 50]],
            ['bytes=50-', 100, (object)['rangefrom' => 50, 'rangeto' => 99, 'length' => 50]],
            ['bytes=50-80/100', 100, (object)['rangefrom' => 50, 'rangeto' => 80, 'length' => 31]],
        ];
    }

    /**
     * Test get_valid_http_ranges() returns range object depending on $_SERVER['HTTP_RANGE'] and file size.
     *
     * @dataProvider get_valid_http_ranges_provider
     *
     * @param string $httprangeheader HTTP_RANGE header.
     * @param int    $filesize        File size.
     * @param mixed  $expectedresult  Expected result.
     */
    public function test_get_valid_http_ranges($httprangeheader, $filesize, $expectedresult) {
        $_SERVER['HTTP_RANGE'] = $httprangeheader;
        $actual = $this->filesystem->get_valid_http_ranges($filesize);
        $this->assertEquals($expectedresult, $actual);
    }

    /**
     * Data provider for test_curl_range_request_to_presigned_url().
     *
     * @return array
     */
    public function curl_range_request_to_presigned_url_provider() {
        return [
            ['15-bytes string', (object)['rangefrom' => 0, 'rangeto' => 14, 'length' => 15], '15-bytes string'],
            ['15-bytes string', (object)['rangefrom' => 0, 'rangeto' => 9, 'length' => 10], '15-bytes s'],
            ['15-bytes string', (object)['rangefrom' => 5, 'rangeto' => 14, 'length' => 10], 'tes string'],
        ];
    }

    /**
     * Test external client curl_range_request_to_presigned_url() returns expected result.
     *
     * @dataProvider curl_range_request_to_presigned_url_provider
     *
     * @param string $content        File content.
     * @param mixed  $ranges         Request ranges object.
     * @param string $expectedresult Expected result.
     */
    public function test_curl_range_request_to_presigned_url($content, $ranges, $expectedresult) {
        if (!$this->filesystem->get_external_client()->support_presigned_urls()) {
            $this->markTestSkipped('Pre-signed URLs not supported for given storage.');
        }
        $file = $this->create_remote_file($content);
        $externalclient = $this->filesystem->get_external_client();
        // Test good response.
        $actual = $externalclient->curl_range_request_to_presigned_url($file->get_contenthash(), $ranges, []);
        $this->assertEquals($expectedresult, $actual['content']);
        $this->assertEquals('206 Partial Content', manager::get_header($actual['responseheaders'], 'HTTP/1.1'));
        // Test bad response.
        $actual = $externalclient->curl_range_request_to_presigned_url($file->get_contenthash() . '_fake', $ranges, []);
        $this->assertEquals('404 Not Found', manager::get_header($actual['responseheaders'], 'HTTP/1.1'));
    }

    /**
     * Test external client test_range_request() method.
     */
    public function test_test_range_request() {
        $externalclient = $this->filesystem->get_external_client();
        if ($externalclient->support_presigned_urls()) {
            $this->assertTrue($externalclient->test_range_request($this->filesystem)->result);
        } else {
            $this->assertFalse($externalclient->test_range_request($this->filesystem)->result);
        }
    }

    /**
     * Test that is_configured() returns true by default.
     */
    public function test_is_configured_default() {
        $this->assertTrue($this->filesystem->is_configured());
    }

    /**
     * Test that is_configured() returns false when the client SDK does not exist.
     */
    public function test_is_configured_fake_autoloader() {
        $this->assertTrue($this->filesystem->is_configured());
        $clientref = new \ReflectionClass($this->filesystem->externalclient);
        $autoloaderref = $clientref->getParentClass()->getProperty('autoloader');
        $autoloaderref->setAccessible(true);
        $autoloader = $autoloaderref->getValue($this->filesystem->externalclient);
        $this->set_externalclient_config('autoloader', $autoloader . '_fake');
        $this->assertFalse($this->filesystem->is_configured());
    }

    /**
     * Test that is_configured() returns false when filesystem set in config.php
     * and filesystem set via admin settings do not match.
     */
    public function test_is_configured_settings_do_not_match() {
        global $CFG;
        $this->assertTrue($this->filesystem->is_configured());
        $CFG->alternative_file_system_class = 'fake_file_system';
        $this->assertFalse($this->filesystem->is_configured());
    }

    /**
     * Test that is_configured() returns false when alternative_file_system_class is not set in config.php.
     */
    public function test_is_configured_alternative_file_system_class_is_not_set() {
        global $CFG;
        $this->assertTrue($this->filesystem->is_configured());
        unset($CFG->alternative_file_system_class);
        $this->assertFalse($this->filesystem->is_configured());
    }

    /**
     * Test that add_file_from_path() is fine if manager::update_object_by_hash() throws an exception
     *
     * @covers ::add_file_from_path
     */
    public function test_add_file_from_path_update_object_fail() {
        global $DB;

        $error = "ERROR!";

        $logger = $this->filesystem->get_logger();
        $mocklogger = $this->createMock(get_class($logger));
        $mocklogger->expects($this->once())->method('error_log')->with($error);
        $this->filesystem->set_logger($mocklogger);

        $db = $DB;
        $DB = $this->createStub(get_class($DB));
        $DB->method('get_record')->willReturnCallback(function ($table, $params) use ($db, $error) {
            throw new \Exception($error);
        });

        $result = $this->filesystem->add_file_from_path(__FILE__);

        $DB = $db;

        $this->assertGreaterThan(0, strlen($result[0]));
        $this->assertGreaterThan(0, $result[1]);
        $this->assertTrue($result[2]);
    }

    /**
     * Test that add_file_from_string() is fine if manager::update_object_by_hash() throws an exception
     *
     * @covers ::add_file_from_string
     */
    public function test_add_file_from_string_update_object_fail() {
        global $DB;

        $content = "Hi!";
        $contenthash = \file_storage::hash_from_string($content);
        $error = "ERROR!";

        $logger = $this->filesystem->get_logger();
        $mocklogger = $this->createMock(get_class($logger));
        $mocklogger->expects($this->once())->method('error_log')->with($error);
        $this->filesystem->set_logger($mocklogger);

        $db = $DB;
        $DB = $this->createStub(get_class($DB));
        $DB->method('get_record')->willReturnCallback(function ($table, $params) use ($db, $error) {
            throw new \Exception($error);
        });

        $result = $this->filesystem->add_file_from_string($content);

        $DB = $db;

        $this->assertEquals($contenthash, $result[0]);
        $this->assertEquals(\core_text::strlen($content), $result[1]);
        $this->assertTrue($result[2]);
    }
}
