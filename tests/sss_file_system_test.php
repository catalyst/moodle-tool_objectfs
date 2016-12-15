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
 * local_catdeleter scheduler tests.
 *
 * @package   local_catdeleter
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once(__DIR__ . '/mock/sss_mock_client.php');
require_once(__DIR__ . '/mock/sss_mock_file_system.php');
require_once(__DIR__ . '/testlib.php');

use tool_sssfs\file_manipulators\pusher;
use tool_sssfs\sss_file_system;


class tool_sssfs_sss_file_system_testcase extends advanced_testcase {

    protected function setUp() {
        global $CFG;
        $this->resetAfterTest(true);
        $CFG->filesystem_handler_class = '\tool_sssfs\sss_file_system';
        $this->config = generate_config();
        $this->client = new sss_mock_client();
        $this->filesystem = sss_file_system::instance();
        $this->filesystem->set_sss_client($this->client);
    }

    protected function tearDown() {

    }

    public function test_get_local_content_from_contenthash() {
        $expectedcontent = 'This is my files content';
        $file = save_file_to_local_storage(100, 'testfile.txt', $expectedcontent);
        $filecontenthash = $file->get_contenthash();
        $actualcontent = $this->filesystem->get_local_content_from_contenthash($filecontenthash);
        $this->assertEquals($expectedcontent, $actualcontent);
    }

    public function test_get_local_content_from_contenthash_throws_exception() {
        $filecontenthash = 'not_a_contenthash';
        $this->setExpectedExceptionRegexp('\core_files\filestorage\file_exception',
            '/Can not read file, either file does not exist or there are permission problems/');
        $actualcontent = $this->filesystem->get_local_content_from_contenthash($filecontenthash);
    }

    public function test_delete_local_file_from_contenthash() {
        $file = save_file_to_local_storage();
        $isreadable = $this->filesystem->is_readable($file);
        $this->assertTrue($isreadable);
        $filecontenthash = $file->get_contenthash();
        $this->filesystem->delete_local_file_from_contenthash($filecontenthash);
        $isreadable = $this->filesystem->is_readable($file);
        $this->assertFalse($isreadable);
    }

    public function test_delete_local_file_from_contenthash_throws_exception() {
        $filecontenthash = 'not_a_contenthash';
        $this->setExpectedExceptionRegexp('\core_files\filestorage\file_exception',
            '/Can not read file, either file does not exist or there are permission problems/');
        $this->filesystem->delete_local_file_from_contenthash($filecontenthash);
    }

    public function test_readfile() {
        $expectedcontent = 'This is my files content';
        $file = save_file_to_local_storage(100, 'testfile.txt', $expectedcontent);
        $filecontenthash = $file->get_contenthash();
        $this->client->push_file($filecontenthash, $expectedcontent);
        $this->filesystem->delete_local_file_from_contenthash($filecontenthash);
        $this->expectOutputString($expectedcontent);
        $this->filesystem->readfile($file);
    }

    public function test_get_content() {
        $expectedcontent = 'This is my files content';
        $file = save_file_to_local_storage(100, 'testfile.txt', $expectedcontent);
        $filecontenthash = $file->get_contenthash();
        $this->client->push_file($filecontenthash, $expectedcontent);
        $this->filesystem->delete_local_file_from_contenthash($filecontenthash);
        $actualcontent = $this->filesystem->get_content($file);
        $this->assertEquals($expectedcontent, $actualcontent);
    }




}
