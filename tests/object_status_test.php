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
use tool_objectfs\local\report\object_status_history_table;
use tool_objectfs\local\report\object_location_history_table;

require_once(__DIR__ . '/classes/test_client.php');
require_once(__DIR__ . '/tool_objectfs_testcase.php');

class object_status_testcase extends tool_objectfs_testcase {

    /**
     * Clean up after each test.
     */
    protected function tearDown() {
        global $DB;
        $DB->delete_records('tool_objectfs_reports');
        parent::tearDown();
    }

    /**
     * Test that generate_status_report creates a snapshot of report.
     */
    public function test_generate_status_report() {
        objectfs_report::generate_status_report();
        $reports = objectfs_report::get_report_ids();
        $this->assertEquals(1, count($reports));
    }

    /**
     * Test that tool_objectfs_reports table holds historic data.
     */
    public function test_generate_status_report_historic() {
        objectfs_report::generate_status_report();
        objectfs_report::generate_status_report();
        $reports = objectfs_report::get_report_ids();
        $this->assertEquals(2, count($reports));
    }

    /**
     * Test that get_report_types returns an array of report types.
     */
    public function test_get_report_types() {
        $reporttypes = objectfs_report::get_report_types();
        $this->assertEquals('array', gettype($reporttypes));
        $this->assertEquals(3, count($reporttypes));
    }

    /**
     * Test that object_status_history_table has correct location section.
     */
    public function test_object_status_history_table_location() {
        global $CFG;
        objectfs_report::generate_status_report();
        $reports = objectfs_report::get_report_ids();
        $table = new object_status_history_table('location', key($reports));
        $table->define_baseurl($CFG->wwwroot);
        $table->setup();
        $table->query_db(100, false);
        // 7 is expected number of rows for location section of Object status report.
        $this->assertEquals(7, count($table->rawdata));
    }

    /**
     * Test that object_status_history_table has correct log_size section.
     */
    public function test_object_status_history_table_log_size() {
        global $DB, $CFG;
        $DB->delete_records('files');
        $this->create_local_file('local file');
        objectfs_report::generate_status_report();
        $reports = objectfs_report::get_report_ids();
        $table = new object_status_history_table('log_size', key($reports));
        $table->define_baseurl($CFG->wwwroot);
        $table->setup();
        $table->query_db(100, false);
        $this->assertEquals(1, count($table->rawdata));
        $this->assertEquals('< 1KB', $table->rawdata[0]['reporttype']);
        $this->assertEquals('1', strip_tags($table->rawdata[0]['files']));
        $this->assertEquals('10 bytes', strip_tags($table->rawdata[0]['size']));
    }

    /**
     * Test that object_status_history_table has correct mime_type section.
     */
    public function test_object_status_history_table_mime_type() {
        global $DB, $CFG;
        $DB->delete_records('files');
        $this->create_local_file('local file');
        objectfs_report::generate_status_report();
        $reports = objectfs_report::get_report_ids();
        $table = new object_status_history_table('mime_type', key($reports));
        $table->define_baseurl($CFG->wwwroot);
        $table->setup();
        $table->query_db(100, false);
        $this->assertEquals(1, count($table->rawdata));
        $this->assertEquals('', $table->rawdata[0]['reporttype']);
        $this->assertEquals('1', strip_tags($table->rawdata[0]['files']));
        $this->assertEquals('10 bytes', strip_tags($table->rawdata[0]['size']));
    }

    /**
     * Test that object_location_history_table has records.
     */
    public function test_object_location_history_table() {
        global $DB, $CFG;
        $DB->delete_records('files');
        $this->create_local_file('local file');
        $this->create_duplicated_file('duplicated file');
        $this->create_remote_file('external file');
        objectfs_report::generate_status_report();
        $table = new object_location_history_table();
        $table->define_baseurl($CFG->wwwroot);
        $table->setup();
        $table->query_db(100, false);
        $this->assertEquals(1, count($table->rawdata));
        $row = reset($table->rawdata);
        $this->assertEquals(1, $row['local_count']);
        $this->assertEquals(1, $row['duplicated_count']);
        $this->assertEquals(1, $row['external_count']);
        $this->assertEquals(3, $row['total_count']);
    }

    /**
     * Test that cleanup_reports deletes old data.
     */
    public function test_cleanup_reports() {
        global $DB;
        objectfs_report::generate_status_report();
        $reports = objectfs_report::get_report_ids();
        $this->assertEquals(1, count($reports));
        $record = new \stdClass();
        $record->id = key($reports);
        $record->reportdate = time() - YEARSECS - 1;
        $DB->update_record('tool_objectfs_reports', $record);
        objectfs_report::cleanup_reports();
        $reports = objectfs_report::get_report_ids();
        $this->assertEquals(0, count($reports));
    }
}
