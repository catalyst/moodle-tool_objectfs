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
 * tool_sssfs file manipulator tests.
 *
 * @package   local_catdeleter
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once( __DIR__ . '/tool_sssfs_testcase.php');

use tool_sssfs\file_manipulators\cleaner;
use tool_sssfs\file_manipulators\puller;
use tool_sssfs\file_manipulators\pusher;


class tool_sssfs_file_manipulators_testcase extends tool_sssfs_testcase {

    protected function setUp() {
        global $CFG;
        $this->resetAfterTest(true);
        $this->config = $this->generate_config();
        $this->client = $this->get_test_client();
        ob_start();
    }

    protected function tearDown() {
        ob_end_clean();
    }

    public function test_cleaner_can_clean_file() {
        global $DB;
        $file = $this->save_file_to_local_storage_from_string();
        $filecontenthash = $file->get_contenthash();
        $this->move_file_to_sss($file, SSS_FILE_LOCATION_DUPLICATED);
        $filecleaner = new cleaner($this->config, $this->client);
        $candidatefiles = $filecleaner->get_candidate_files();
        $recors = $DB->get_records('tool_sssfs_filestate');
        $this->assertEquals(1, count($candidatefiles));
        $candidatefile = reset($candidatefiles); // Reset array so key fives us key of first value.
        $this->assertEquals($filecontenthash, $candidatefile->contenthash);
        $filecleaner->execute($candidatefiles);
        $fullpath = $this->get_local_fullpath_from_hash($filecontenthash);
        $isreadable = is_readable($fullpath);
        $this->assertFalse($isreadable);
    }

    public function test_cleaner_consisency_delay() {
        $this->config = $this->generate_config(0, -10, 60, 0); // Set deletelocal to 0.
        $filecleaner = new cleaner($this->config, $this->client);
        $file = $this->save_file_to_local_storage_from_string();
        $filecontenthash = $file->get_contenthash();
        log_file_state($filecontenthash, SSS_FILE_LOCATION_DUPLICATED, 'bogusmd5'); // Save file as already duplicated.
        $candidatehashes = $filecleaner->get_candidate_files();
        $filecleaner->execute($candidatehashes); // Should not delete the file.
        $fullpath = $this->get_local_fullpath_from_hash($filecontenthash);
        $isreadable = is_readable($fullpath);
        $this->assertTrue($isreadable);
    }

    public function test_pusher_can_push_file() {
        global $DB;
        $filepusher = new pusher($this->config, $this->client);
        $file = $this->save_file_to_local_storage_from_string();
        $filecontenthash = $file->get_contenthash();
        $contenthashes = $filepusher->get_candidate_files();
        $filepusher->execute($contenthashes);
        $postpushcount = $DB->count_records('tool_sssfs_filestate', array('contenthash' => $filecontenthash));
        $this->assertEquals(1, $postpushcount); // Assert table has item.
    }

    public function test_pusher_wont_push_file_under_threshold() {
        global $DB;

        // Set size threshold of 1000.
        $this->config = $this->generate_config(1000);
        $filepusher = new pusher($this->config, $this->client);
        $file = $this->save_file_to_local_storage_from_string(100); // Set file size to 100.
        $filecontenthash = $file->get_contenthash();
        $contenthashes = $filepusher->get_candidate_files();
        $filepusher->execute($contenthashes);
        $postpushcount = $DB->count_records('tool_sssfs_filestate', array('contenthash' => $filecontenthash));

        // Assert table still does not contain entry.
        $this->assertEquals(0, $postpushcount);
    }

    public function test_pusher_under_minimum_age_files_are_not_pushed() {
        global $DB;

        // Set minimum age to a large value.
        $this->config = $this->generate_config(0, 99999);
        $filepusher = new pusher($this->config, $this->client);
        $file = $this->save_file_to_local_storage_from_string();
        $filecontenthash = $file->get_contenthash();
        $contenthashes = $filepusher->get_candidate_files();
        $filepusher->execute($contenthashes);
        $postpushcount = $DB->count_records('tool_sssfs_filestate', array('contenthash' => $filecontenthash));

        // Assert table still does not contain entry.
        $this->assertEquals(0, $postpushcount);
    }


    public function test_pusher_sss_client_wont_push_file_that_is_not_there () {
        global $DB;
        $filepusher = new pusher($this->config, $this->client);
        $file = new stdClass();
        $file->contenthash = 'not_a_hash';
        $filepusher->execute(array($file));
        $postpushcount = $DB->count_records('tool_sssfs_filestate', array('contenthash' => $file->contenthash));
        $this->assertEquals(0, $postpushcount); // Assert table still does not contain entry.
    }

    public function test_pusher_max_task_runtime () {
        global $DB;

        // Set max runtime to 0.
        $this->config = $this->generate_config(0, -10, 0);

        $filepusher = new pusher($this->config, $this->client);
        $file = $this->save_file_to_local_storage_from_string();
        $filecontenthash = $file->get_contenthash();
        $contenthashes = $filepusher->get_candidate_files();
        $filepusher->execute($contenthashes);
        $postpushcount = $DB->count_records('tool_sssfs_filestate', array('contenthash' => $filecontenthash));

        // Assert table does not contain entry.
        $this->assertEquals(0, $postpushcount);
    }

    public function test_pusher_saves_md5_hash () {
        global $DB;

        $filepusher = new pusher($this->config, $this->client);
        $file = $this->save_file_to_local_storage_from_string();
        $expectedcontent = 'This is my files content';
        $file = $this->save_file_to_local_storage_from_string(100, 'testfile.txt', $expectedcontent);
        $filecontenthash = $file->get_contenthash();
        $expectedmd5 = md5($expectedcontent);
        $contenthashes = $filepusher->get_candidate_files();
        $filepusher->execute($contenthashes);
        $savedrecord = $DB->get_record('tool_sssfs_filestate', array('contenthash' => $filecontenthash));
        $this->assertEquals($expectedmd5, $savedrecord->md5);
    }

    public function test_puller_can_pull_file() {
        global $DB;
        $this->config = $this->generate_config(999999); // It will be pulled back.
        $filepuller = new puller($this->config, $this->client);
        $file = $this->save_file_to_local_storage_from_string();
        $filecontenthash = $file->get_contenthash();
        $this->move_file_to_sss($file);
        $files = $filepuller->get_candidate_files();
        $filepuller->execute($files);
        $postpushcount = $DB->count_records('tool_sssfs_filestate', array('contenthash' => $filecontenthash, 'location' => SSS_FILE_LOCATION_DUPLICATED));
        $this->assertEquals(1, $postpushcount); // Assert table has item.
    }

}
