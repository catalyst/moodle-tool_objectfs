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
 * tool_objectfs file status tests.
 *
 * @package   tool_objectfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 namespace tool_objectfs\tests;

 defined('MOODLE_INTERNAL') || die();

 use tool_objectfs\local\report\objectfs_report_builder;
 use tool_objectfs\local\report\objectfs_report;

 require_once(__DIR__ . '/classes/test_client.php');
 require_once(__DIR__ . '/tool_objectfs_testcase.php');

class object_status_testcase extends tool_objectfs_testcase {

    /**
     * Test that generate_status_report a snapshot of report.
     */
    public function test_generate_status_report() {
        global $DB;
        $DB->delete_records('tool_objectfs_reports');
        objectfs_report::generate_status_report();
        $dates = objectfs_report::get_report_dates();
        $this->assertEquals(1, count($dates));
    }

    /**
     * Test that tool_objectfs_reports table holds historic data.
     */
    public function test_generate_status_report_historic() {
        global $DB;
        $DB->delete_records('tool_objectfs_reports');
        objectfs_report::generate_status_report();
        objectfs_report::generate_status_report();
        $dates = objectfs_report::get_report_dates();
        $this->assertEquals(2, count($dates));
    }

    /**
     * Test that load_report_from_database returns report object.
     */
    public function test_load_report_from_database() {
        global $DB;
        $DB->delete_records('tool_objectfs_reports');
        objectfs_report::generate_status_report();
        $reporttypes = objectfs_report::get_report_types();
        foreach ($reporttypes as $reporttype) {
            $report = objectfs_report_builder::load_report_from_database($reporttype);
            $this->assertEquals('tool_objectfs\local\report\objectfs_report', get_class($report));
        }
    }

    /**
     * Test that get_report_types returns an array of report types.
     */
    public function test_get_report_types() {
        $reporttypes = objectfs_report::get_report_types();
        $this->assertEquals('array', gettype($reporttypes));
        $this->assertEquals(3, count($reporttypes));
    }
}
