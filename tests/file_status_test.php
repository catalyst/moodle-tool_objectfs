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
 * tool_sssfs file status tests.
 *
 * @package   local_catdeleter
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once( __DIR__ . '/tool_sssfs_testcase.php');

use tool_sssfs\report\file_location_report;
use tool_sssfs\report\log_size_report;
use tool_sssfs\report\mime_type_report;
use tool_sssfs\file_manipulators\pusher;

class tool_sssfs_file_status_testcase extends tool_sssfs_testcase {

    protected function setUp() {
        global $CFG;
        $this->resetAfterTest(true);
        $CFG->filesystem_handler_class = '\tool_sssfs\sss_file_system';
        $this->config = $this->generate_config();
        $this->client = $this->get_test_client();
    }

    protected function tearDown() {

    }

    private function check_file_location_record($record, $expectedcount, $expectedsum) {
        $this->assertEquals($expectedcount, $record->filecount);
        $this->assertEquals($expectedsum, $record->filesum);
    }


    public function test_calculate_file_location_data () {

        $report = new file_location_report();

        $data = $report->calculate_report_data();

        // Duplicated and external states should be 0 for sum and count.
        $this->check_file_location_record($data[SSS_FILE_LOCATION_DUPLICATED], 0, 0);
        $this->check_file_location_record($data[SSS_FILE_LOCATION_EXTERNAL], 0, 0);

        $this->config = $this->generate_config(10); // 10 MB size threshold.
        $pusher = new pusher($this->config, $this->client);

        $singlefilesize = 100 * 1024; // 100mb.
        for ($i = 1; $i <= 10; $i++) {
            $this->save_file_to_local_storage_from_string(1024 * 100, "test-{$i}.txt", "test-{$i} content"); // 100 mb files.
        }

        $contenthashes = $pusher->get_candidate_files();
        $pusher->execute($contenthashes);

        $data = $report->calculate_report_data();

        $this->check_file_location_record($data[SSS_FILE_LOCATION_DUPLICATED], 10, $singlefilesize * 10);

        $report->save_report_data($data);
    }

    public function test_calculate_file_logsize_data () {
        $report = new log_size_report();
        $data = $report->calculate_report_data();
        $report->save_report_data($data);
    }

    public function test_calculate_file_mimetype_data () {
        $report = new mime_type_report();
        $data = $report->calculate_report_data();
        $report->save_report_data($data);
    }


}