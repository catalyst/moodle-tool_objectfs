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
require_once(__DIR__ . '/testlib.php');

use tool_sssfs\sss_file_system;
use tool_sssfs\file_manipulators\cleaner;

class tool_sssfs_cleaner_testcase extends advanced_testcase {


    protected function setUp() {
        global $CFG;
        $this->resetAfterTest(true);
        $CFG->filesystem_handler_class = '\tool_sssfs\sss_file_system';
        $this->config = generate_config();
        $this->client = new sss_mock_client();
        $this->filesystem = sss_file_system::instance();
    }

    protected function tearDown() {

    }

    public function test_can_clean_file() {
        global $DB;
        $file = save_file_to_local_storage();
        $filecontenthash = $file->get_contenthash();
        log_file_state($filecontenthash, SSS_FILE_LOCATION_DUPLICATED); // Save file as already duplicated.
        $filecleaner = new cleaner($this->client, $this->filesystem, $this->config);
        $candidatehashes = $filecleaner->get_candidate_content_hashes();
        $candidatehash = reset($candidatehashes);
        $this->assertEquals(1, count($candidatehashes));
        $this->assertEquals($filecontenthash, $candidatehash);
        $filecleaner->execute($candidatehashes);
        $isreadable = $this->filesystem->is_readable($file);
        $this->assertFalse($isreadable);
    }

    public function test_consisency_delay() {
        $this->config = generate_config(0, -10, 60, 0); // Set deletelocal to 0.
        $filecleaner = new cleaner($this->client, $this->filesystem, $this->config);
        $file = save_file_to_local_storage();
        $filecontenthash = $file->get_contenthash();
        log_file_state($filecontenthash, SSS_FILE_LOCATION_DUPLICATED); // Save file as already duplicated.
        $candidatehashes = $filecleaner->get_candidate_content_hashes();
        $filecleaner->execute($candidatehashes); // Should not delete the file.
        $isreadable = $this->filesystem->is_readable($file);
        $this->assertTrue($isreadable);
    }

}
