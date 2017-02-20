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

 namespace tool_objectfs\tests;

 defined('MOODLE_INTERNAL') || die();

 use tool_objectfs\object_file_system;
 use tool_objectfs\report\object_location_report;
 use tool_objectfs\report\log_size_report;
 use tool_objectfs\report\mime_type_report;

 require_once(__DIR__ . '/test_client.php');
 require_once(__DIR__ . '/tool_objectfs_testcase.php');


class object_status_testcase extends tool_objectfs_testcase {

    public function test_object_location_report () {
        $report = new object_location_report();
        $data = $report->calculate_report_data();
        $report->save_report_data($data);
    }

    public function test_log_size_report () {
        $report = new log_size_report();
        $data = $report->calculate_report_data();
        $report->save_report_data($data);
    }

    public function test_mime_type_report () {
        $report = new mime_type_report();
        $data = $report->calculate_report_data();
        $report->save_report_data($data);
    }
}