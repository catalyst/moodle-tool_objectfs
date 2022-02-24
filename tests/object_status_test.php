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

use tool_objectfs\local\report\objectfs_report;
use tool_objectfs\local\report\object_status_history_table;
use tool_objectfs\local\report\object_location_history_table;
use tool_objectfs\local\report\log_size_report_builder;

require_once(__DIR__ . '/classes/test_client.php');
require_once(__DIR__ . '/tool_objectfs_testcase.php');

class object_status_testcase extends tool_objectfs_testcase {

    /**
     * Clean up after each test.
     */
    protected function tearDown(): void {
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
        // 8 is expected number of rows for location section of Object status report.
        $this->assertEquals(8, count($table->rawdata));
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
        $record = reset($table->rawdata);
        $this->assertEquals('1', $record->heading);
        $this->assertEquals('1', $record->count);
        $this->assertEquals('10', $record->size);
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
        $record = reset($table->rawdata);
        $this->assertEquals('', $record->heading);
        $this->assertEquals('1', $record->count);
        $this->assertEquals('10', $record->size);
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

    /**
     * Data provider for test_object_status_add_barchart_method().
     *
     * @return array
     */
    public function test_object_status_add_barchart_method_provider() {
        return [
            [0, 0, '', 0, '0'],
            [0, 100, 'count', 0, '<div class="ofs-bar" style="width:0%">' . number_format(0) . '</div>'],
            [0, 100, 'size', 0, '<div class="ofs-bar" style="width:0%">' . display_size(0) . '</div>'],
            [0, 100, 'runningsize', 0,
                '<div class="ofs-bar" style="width:0%">' . number_format(0) . '% (' . display_size(0) . ')</div>'],
            [0, 100, 'count', 2, '<div class="ofs-bar" style="width:0%">' . number_format(0) . '</div>'],
            [0, 100, 'size', 2, '<div class="ofs-bar" style="width:0%">' . display_size(0) . '</div>'],
            [0, 100, 'runningsize', 2,
                '<div class="ofs-bar" style="width:0%">' . number_format(0, 2) . '% (' . display_size(0) . ')</div>'],
            [10, 100, 'count', 2, '<div class="ofs-bar" style="width:10%">' . number_format(10) . '</div>'],
            [10, 100, 'size', 2, '<div class="ofs-bar" style="width:10%">' . display_size(10) . '</div>'],
            [10, 100, 'runningsize', 2,
                '<div class="ofs-bar" style="width:10%">' . number_format(10, 2) . '% (' . display_size(10) . ')</div>'],
            [12345678, 123456789, 'count', 0, '<div class="ofs-bar" style="width:10%">' . number_format(12345678) . '</div>'],
            [12345678, 123456789, 'size', 0, '<div class="ofs-bar" style="width:10%">' . display_size(12345678) . '</div>'],
            [12345678, 123456789, 'runningsize', 2,
                '<div class="ofs-bar" style="width:10%">' . number_format(10, 2) . '% (' . display_size(12345678) . ')</div>'],
        ];
    }

    /**
     * Test add_barchart() returns correct HTML string.
     *
     * @dataProvider test_object_status_add_barchart_method_provider
     *
     * @param  int    $value     Table cell value
     * @param  int    $max       Maximum value for a given column
     * @param  string $type      Column type (count, size or runningsize)
     * @param  int    $precision The optional number of decimal digits to round to
     * @param  string $expected  Expected result
     */
    public function test_object_status_add_barchart_method($value, $max, $type, $precision, $expected) {
        $table = new object_status_history_table('location', 0);
        $actual = $table->add_barchart($value, $max, $type, $precision);
        $this->assertEquals($expected, $actual);
    }

    /**
     * Data provider for test_object_status_get_size_range_from_logsize().
     *
     * @return array
     */
    public function test_object_status_get_size_range_from_logsize_provider() {
        return [
            ['1', '< ' . display_size(1024)],
            ['10', display_size(1024) . ' - ' . display_size(2048)],
            ['11', display_size(2048) . ' - ' . display_size(4096)],
            ['20', display_size(1048576) . ' - ' . display_size(2097152)],
            ['21', display_size(2097152) . ' - ' . display_size(4194304)],
            ['30', display_size(1073741824) . ' - ' . display_size(2147483648)],
            ['31', display_size(2147483648) . ' - ' . display_size(4294967296)],
        ];
    }

    /**
     * Test get_size_range_from_logsize() returns correct HTML string.
     *
     * @dataProvider test_object_status_get_size_range_from_logsize_provider
     *
     * @param  string $logsize   Log size to be ranged
     * @param  string $expected  Expected result
     */
    public function test_object_status_get_size_range_from_logsize($logsize, $expected) {
        $table = new object_status_history_table('location', 0);
        $actual = $table->get_size_range_from_logsize($logsize);
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test that compress_small_log_sizes() correctly compresses small entries.
     */
    public function test_compress_small_log_sizes() {
        $stats = [
            (object)['datakey' => 1, 'objectsum' => 1, 'objectcount' => 10],
            (object)['datakey' => 5, 'objectsum' => 1, 'objectcount' => 10],
            (object)['datakey' => 9, 'objectsum' => 1, 'objectcount' => 10],
            (object)['datakey' => 10, 'objectsum' => 1, 'objectcount' => 10],
            (object)['datakey' => 15, 'objectsum' => 1, 'objectcount' => 10],
        ];
        $expected = [
            (object)['datakey' => 1, 'objectsum' => 3, 'objectcount' => 30],
            (object)['datakey' => 10, 'objectsum' => 1, 'objectcount' => 10],
            (object)['datakey' => 15, 'objectsum' => 1, 'objectcount' => 10],
        ];
        $builder = new log_size_report_builder();
        $builder->compress_small_log_sizes($stats);
        $this->assertEquals($expected, $stats);
    }
}
