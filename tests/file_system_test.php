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

/**
 * tool_sssfs file system tests.
 *
 * @package   local_catdeleter
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once( __DIR__ . '/tool_sssfs_testcase.php');

use tool_sssfs\sss_file_system;

class tool_sssfs_file_system_testcase extends tool_sssfs_testcase {

    protected function setUp() {
        global $CFG;
        $this->resetAfterTest();
        $CFG->filesystem_handler_class = '\tool_sssfs\sss_file_system';
        $this->config = $this->generate_config();
        sss_file_system::reset(); // Remove old FS if still active.
        $this->client = new sss_mock_client();
        $this->filesystem = sss_file_system::instance();
        $this->filesystem->set_sss_client($this->client);
    }

    private function move_file_to_sss($file) {
        $contenthash = $file->get_contenthash();
        $localpath = $this->get_local_fullpath_from_hash($contenthash);
        $ssspath = $this->client->get_sss_fullpath_from_hash($contenthash);
        rename($localpath, $ssspath);
        log_file_state($contenthash, SSS_FILE_LOCATION_EXTERNAL, 'md5');
    }

    protected function tearDown() {

    }

    public function test_can_sss_readfile() {
        $expectedcontent = 'test expected content';
        $file = $this->save_file_to_local_storage_from_string(10, 'test.txt', $expectedcontent);
        $this->move_file_to_sss($file);
        $this->filesystem->readfile($file);
        $this->expectOutputString($expectedcontent);
    }

    // Should return if readable in local or sss.
    public function test_is_readable() {
        $file = $this->save_file_to_local_storage_from_string();
        $isreadable = $this->filesystem->is_readable($file);
        $this->assertTrue($isreadable);
        $this->move_file_to_sss($file);
        $isreadable = $this->filesystem->is_readable($file);
        $this->assertTrue($isreadable); // Should still be readable.
    }

    // Should return if readable in local or sss.
    public function test_is_readable_by_hash() {
        $file = $this->save_file_to_local_storage_from_string();
        $contenthash = $file->get_contenthash();
        $isreadable = $this->filesystem->is_readable_by_hash($contenthash);
        $this->assertTrue($isreadable);
        $this->move_file_to_sss($file);
        $isreadable = $this->filesystem->is_readable_by_hash($contenthash);
        $this->assertTrue($isreadable); // Should still be readable.
    }

    public function test_ensure_readable() {
        $file = $this->save_file_to_local_storage_from_string();
        $contenthash = $file->get_contenthash();
        $isreadable = $this->filesystem->ensure_readable($file);
        $this->assertTrue($isreadable);
        $this->move_file_to_sss($file);
        $isreadable = $this->filesystem->ensure_readable($file);
        $this->assertTrue($isreadable); // Should still be readable.
        $ssspath = $this->client->get_sss_fullpath_from_hash($contenthash);
        unlink($ssspath);
        $this->setExpectedException('\core_files\filestorage\file_exception');
        $this->filesystem->ensure_readable($file);
    }

    // Should return if readable in local or sss.
    public function test_sss_copy_content_from_storedfile() {
        global $CFG;
        $expectedcontent = 'copy expected content';
        $file = $this->save_file_to_local_storage_from_string(10, 'test.txt', $expectedcontent);
        $this->move_file_to_sss($file);
        $target = $CFG->dataroot . '/filedir/target'; // Will get cleaned up after test.
        $this->filesystem->copy_content_from_storedfile($file, $target);
        $targetcontents = file_get_contents($target);
        $this->assertEquals($expectedcontent, $targetcontents);
    }

    public function test_sss_try_content_recovery() {
        $file = $this->save_file_to_local_storage_from_string();
        $this->move_file_to_sss($file);
        $result = $this->filesystem->try_content_recovery($file);
        // TODO: expand on this test and nail down expected behaviour.
    }

    public function deleted_file_cleanup_does_not_delete_sss_files() {
        $file = $this->save_file_to_local_storage_from_string();
        $contenthash = $file->get_contenthash();
        $this->move_file_to_sss($file);
        $result = $this->filesystem->deleted_file_cleanup($contenthash);
        $ssspath = $this->client->get_sss_fullpath_from_hash($contenthash);
        $this->assertTrue(is_readable($ssspath)); // Should be true cause it still exists.
    }

    public function test_sss_get_content() {
        $expectedcontent = 'test expected content';
        $file = $this->save_file_to_local_storage_from_string(10, 'test.txt', $expectedcontent);
        $this->move_file_to_sss($file);
        $actualcontent = $this->filesystem->get_content($file);
        $this->assertEquals($expectedcontent, $actualcontent);
    }

    public function test_sss_list_files() {
        $zipfile = $this->generate_zip_archive_file();
        $packer = get_file_packer('application/zip');
        $this->move_file_to_sss($zipfile);
        $result = $this->filesystem->list_files($zipfile, $packer);
        $this->assertEquals('fileone', $result[0]->pathname);
        $this->assertEquals('filetwo', $result[1]->pathname);
        $this->assertEquals('filethree', $result[2]->pathname);
    }

    private function generate_zip_archive_file() {
        global $CFG;
        $packer = get_file_packer('application/zip');
        $file = __DIR__.'/fixtures/test.txt';

        $files = array(
            'fileone' => $file,
            'filetwo' => $file,
            'filethree' => $file
        );
        $archivepath = $CFG->dataroot . '/temp/testarchive.zip';
        $packer->archive_to_pathname($files, $archivepath);

        $file = $this->save_file_to_local_storage_from_pathname($archivepath);

        return $file;
    }

    public function test_sss_extract_to_pathname() {
        global $CFG;
        $zipfile = $this->generate_zip_archive_file();
        $packer = get_file_packer('application/zip');
        $this->move_file_to_sss($zipfile);

        $extractdir = $CFG->dataroot . '/temp/testextract';
        mkdir($extractdir);
        $result = $this->filesystem->extract_to_pathname($zipfile, $packer, $extractdir);
        foreach ($result as $success) {
            $this->assertTrue($success);
        }
    }

    public function test_sss_add_to_curl_request() {
        $file = $this->save_file_to_local_storage_from_string();
        $contenthash = $file->get_contenthash();
        $this->move_file_to_sss($file);
        $curl = new curl();
        $testkey = 'testkey';
        $this->filesystem->add_to_curl_request($file, $curl, $testkey);
        $expectedpath = $this->client->get_sss_fullpath_from_hash($contenthash);
        $curlpath = $curl->_tmp_file_post_params[$testkey]->name;
        $this->assertEquals($expectedpath, $curlpath);
    }

    public function test_extract_to_storage() {
        global $CFG;
        $zipfile = $this->generate_zip_archive_file();
        $packer = get_file_packer('application/zip');
        $this->move_file_to_sss($zipfile);

        // These were added to the zip archive.
        $testfile = __DIR__.'/fixtures/test.txt';

        $syscontext = 1;
        $component = 'core';
        $filearea  = 'unittest';
        $itemid    = 0;
        $filepath  = '/';

        $result = $this->filesystem->extract_to_storage($zipfile, $packer, $syscontext, $component, $filearea, $itemid, $filepath);
        foreach ($result as $success) {
            $this->assertTrue($success);
        }
    }

    public function test_sss_get_imageinfo() {
        $testimage = __DIR__.'/fixtures/testimage.png';
        $file = $this->save_file_to_local_storage_from_pathname($testimage);
        $this->move_file_to_sss($file);
        $actualinfo = $this->filesystem->get_imageinfo($file);
        $expectedinfo = getimagesize($testimage);
        $this->assertEquals($expectedinfo[0], $actualinfo['width']);
        $this->assertEquals($expectedinfo[1], $actualinfo['height']);
        $this->assertEquals($expectedinfo['mime'], $actualinfo['mimetype']);
    }

    public function test_sss_is_image() {
        $testimage = __DIR__.'/fixtures/testimage.png';
        $file = $this->save_file_to_local_storage_from_pathname($testimage);
        $this->move_file_to_sss($file);
        $isimage = $this->filesystem->is_image($file);
        $this->assertTrue($isimage);

        $file = $this->save_file_to_local_storage_from_string();
        $this->move_file_to_sss($file);
        $isimage = $this->filesystem->is_image($file);
        $this->assertFalse($isimage);
    }

    public function test_sss_get_content_file_handle() {
        $file = $this->save_file_to_local_storage_from_string();
        $this->move_file_to_sss($file);
        $handle = $this->filesystem->get_content_file_handle($file);
        $isresource = is_resource($handle);
        $this->assertTrue($isresource);
        fclose($handle);
    }

    public function test_sss_mimetype() {
        $file = $this->save_file_to_local_storage_from_string();
        $contenthash = $file->get_contenthash();
        $this->move_file_to_sss($file);
        $ssspath = $this->client->get_sss_fullpath_from_hash($contenthash);
        $mimetype = $this->filesystem->mimetype($ssspath);
        $expectedmimetype = "text/plain";
        $this->assertEquals($expectedmimetype, $mimetype);
    }

    public function test_sss_mimetype_from_path() {
        $file = $this->save_file_to_local_storage_from_string();
        $contenthash = $file->get_contenthash();
        $this->move_file_to_sss($file);
        $ssspath = $this->client->get_sss_fullpath_from_hash($contenthash);
        $mimetype = $this->filesystem->mimetype_from_path($ssspath);
        $expectedmimetype = "text/plain";
        $this->assertEquals($expectedmimetype, $mimetype);
    }

    public function test_sss_mimetype_from_storedfile() {
        $file = $this->save_file_to_local_storage_from_string();
        $this->move_file_to_sss($file);
        $mimetype = $this->filesystem->mimetype_from_storedfile($file);
        $expectedmimetype = "text/plain";
        $this->assertEquals($expectedmimetype, $mimetype);
    }

    public function test_sss_mimetype_from_hash() {
        $file = $this->save_file_to_local_storage_from_string();
        $contenthash = $file->get_contenthash();
        $this->move_file_to_sss($file);
        $mimetype = $this->filesystem->mimetype_from_hash($contenthash, 'whatever');
        $expectedmimetype = "text/plain";
        $this->assertEquals($expectedmimetype, $mimetype);
    }

    public function test_add_string_to_pool_doesnt_add_if_in_sss() {
        $expectedcontent = 'test expected content';

        // This calls add_string_to_pool with all associated setup.
        $file = $this->save_file_to_local_storage_from_string(10, 'test.txt', $expectedcontent);
        $contenthash = $file->get_contenthash();

        // Assert not readable after we move it to s3.
        $this->move_file_to_sss($file);
        $localpath = $this->get_local_fullpath_from_hash($contenthash);
        $isreadable = is_readable($localpath);

        // Assert still not readable locally after we create another file with the same contenthash.
        $duplicatefile = $this->save_file_to_local_storage_from_string(10, 'dupe.txt', $expectedcontent);
        $localpath = $this->get_local_fullpath_from_hash($contenthash);
        $isreadable = is_readable($localpath);

        // Finaly make sure we can read the duplicate file.
        $actualcontent = $this->filesystem->get_content($duplicatefile);
        $this->assertEquals($expectedcontent, $actualcontent);
    }

    public function test_add_file_to_pool_doesnt_add_if_in_sss() {
        global $CFG;
        $testfilepath = __DIR__.'/fixtures/test.txt';
        $expectedcontent = file_get_contents($testfilepath);

        // This calls add_file_to_pool with all associated setup.
        $file = $this->save_file_to_local_storage_from_pathname($testfilepath);
        $contenthash = $file->get_contenthash();

        // Assert not readable after we move it to s3.
        $this->move_file_to_sss($file);
        $localpath = $this->get_local_fullpath_from_hash($contenthash);
        $isreadable = is_readable($localpath);

        // Lets duplicate the original file.
        $dupefilepath  = $CFG->dataroot . '/temp/duplicatetestfile.txt';
        copy($testfilepath, $dupefilepath);

        // Assert still not readable locally after we create another file with the same contenthash.
        $duplicatefile = $this->save_file_to_local_storage_from_pathname($dupefilepath);
        $localpath = $this->get_local_fullpath_from_hash($contenthash);
        $isreadable = is_readable($localpath);

        // Finaly make sure we can read the duplicate file.
        $actualcontent = $this->filesystem->get_content($duplicatefile);
        $this->assertEquals($expectedcontent, $actualcontent);
    }
}