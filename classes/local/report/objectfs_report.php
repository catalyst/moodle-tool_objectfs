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
 * objectfs report class.
 *
 * @package   tool_objectfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\local\report;

defined('MOODLE_INTERNAL') || die();

class objectfs_report implements \renderable {

    /** @var string $reporttype */
    protected $reporttype = '';

    /** @var int $reportid */
    protected $reportid = 0;

    /** @var array $rows */
    protected $rows = [];

    /**
     * @param string $reporttype
     */
    public function __construct($reporttype, $reportid) {
        $this->reporttype = $reporttype;
        $this->reportid = $reportid;
    }

    /**
     * @param string $datakey
     * @param int $objectcount
     * @param int $objectsum
     */
    public function add_row($datakey, $objectcount, $objectsum) {
        $row = new \stdClass();
        $row->datakey = $datakey;
        $row->objectcount = $objectcount;
        $row->objectsum = $objectsum;
        $this->rows[] = $row;
    }

    /**
     * @param array $rows
     */
    public function add_rows(array $rows) {
        foreach ($rows as $row) {
            $this->add_row($row->datakey, $row->objectcount, $row->objectsum);
        }
    }

    /**
     * @return array
     */
    public function get_rows() {
        return $this->rows;
    }

    /**
     * @return string
     */
    public function get_report_type() {
        return $this->reporttype;
    }

    /**
     * @return int
     */
    public function get_report_id() {
        return $this->reportid;
    }

    /**
     * Saves report snapshot to database.
     *
     * @return void
     * @throws /dml_exception
     */
    public function save_report_to_database() {
        global $DB;

        // Add report type to each row.
        foreach ($this->rows as $row) {
            $row->reporttype = $this->reporttype;
            $row->reportid = $this->reportid;
            // We dont use insert_records because of 26 compatibility.
            $DB->insert_record('tool_objectfs_report_data', $row);
        }
    }

    public static function generate_status_report() {
        global $DB;
        $reportid = $DB->insert_record('tool_objectfs_reports', (object)['reportdate' => time()]);
        $reporttypes = self::get_report_types();

        foreach ($reporttypes as $reporttype) {
            $reportbuilderclass = "tool_objectfs\\local\\report\\{$reporttype}_report_builder";
            $reportbuilder = new $reportbuilderclass();
            $report = $reportbuilder->build_report($reportid);
            $report->save_report_to_database();
        }
    }

    /**
     * Deletes report snapshots older than 1 year.
     *
     * @return void
     * @throws /dml_exception
     */
    public static function cleanup_reports() {
        global $DB;
        $reportdate = time() - YEARSECS;
        $params = array('reportdate' => $reportdate);
        $reports = $DB->get_records_select('tool_objectfs_reports', 'reportdate < :reportdate', $params, 'id', 'id');
        $reportids = array_keys($reports);
        $DB->delete_records_list('tool_objectfs_reports', 'id', $reportids);
        $DB->delete_records_list('tool_objectfs_report_data', 'reportid', $reportids);
    }

    /**
     * @return array
     */
    public static function get_report_types() {
        return [
            'location',
            'log_size',
            'mime_type',
        ];
    }

    /**
     * Returns the list of report snapshots.
     *
     * @return array date options.
     * @throws /dml_exception
     */
    public static function get_report_ids() {
        global $DB;
        $reports = array();
        $records = $DB->get_records('tool_objectfs_reports', null, 'id DESC', 'id, reportdate');
        foreach ($records as $record) {
            $reports[$record->id] = $record->reportdate;
        }
        return $reports;
    }

    /**
     * Formats location string.
     *
     * @param  int|string $filelocation
     * @return string
     * @throws \coding_exception
     */
    public static function get_file_location_string($filelocation) {
        $locationstringmap = [
            'total' => 'object_status:location:total',
            'filedir' => 'object_status:filedir',
            'deltaa' => 'object_status:delta:a',
            'deltab' => 'object_status:delta:b',
            OBJECT_LOCATION_ERROR => 'object_status:location:error',
            OBJECT_LOCATION_LOCAL => 'object_status:location:local',
            OBJECT_LOCATION_DUPLICATED => 'object_status:location:duplicated',
            OBJECT_LOCATION_EXTERNAL => 'object_status:location:external',
        ];
        if (isset($locationstringmap[$filelocation])) {
            return get_string($locationstringmap[$filelocation], 'tool_objectfs');
        }
        return get_string('object_status:location:unknown', 'tool_objectfs');
    }

    /**
     * Adds a barchart to the table cells.
     *
     * @param  object $table
     */
    public static function augment_barchart(&$table) {

        // This assumes 2 columns, the first is a number and the second
        // is a file size.

        foreach (array(1, 2) as $col) {

            $max = 0;
            foreach ($table->data as $row) {
                if ($row[$col] > $max) {
                    $max = $row[$col];
                }
            }

            foreach ($table->data as $i => $row) {
                if ($max != 0) {
                    $table->data[$i][$col] = sprintf('<div class="ofs-bar" style="width:%.1f%%">%s</div>',
                        100 * $row[$col] / $max,
                        $col == 1 ? number_format($row[$col]) : display_size($row[$col])
                    );
                }
            }
        }
    }

    /**
     * Formats size range from log size.
     *
     * @param  string $logsize
     * @return string
     */

    public static function get_size_range_from_logsize($logsize) {

        // Small logsizes have been compressed.
        if ($logsize == 'small') {
            return '< 1KB';
        }

        $floor = pow(2, $logsize);
        $roof = ($floor * 2);
        $floor = display_size($floor);
        $roof = display_size($roof);
        $sizerange = "$floor - $roof";
        return $sizerange;
    }
}
