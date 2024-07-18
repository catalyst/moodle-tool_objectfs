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

/**
 * objectfs_report
 */
class objectfs_report implements \renderable {

    /**
     * reporttype
     * @var string
     */
    protected $reporttype = '';

    /**
     * reportid
     * @var int
     */
    protected $reportid = 0;

    /**
     * rows
     * @var array
     */
    protected $rows = [];

    /**
     * construct
     * @param string $reporttype
     * @param int $reportid
     */
    public function __construct($reporttype, $reportid) {
        $this->reporttype = $reporttype;
        $this->reportid = $reportid;
    }

    /**
     * add_row
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
     * add_rows
     * @param array $rows
     */
    public function add_rows(array $rows) {
        foreach ($rows as $row) {
            $this->add_row($row->datakey, $row->objectcount, $row->objectsum);
        }
    }

    /**
     * get_rows
     * @return array
     */
    public function get_rows() {
        return $this->rows;
    }

    /**
     * get_report_type
     * @return string
     */
    public function get_report_type() {
        return $this->reporttype;
    }

    /**
     * get_report_id
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

    /**
     * generate_status_report
     * @return void
     */
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
        $params = ['reportdate' => $reportdate];
        $reports = $DB->get_records_select('tool_objectfs_reports', 'reportdate < :reportdate', $params, 'id', 'id');
        $reportids = array_keys($reports);
        $DB->delete_records_list('tool_objectfs_reports', 'id', $reportids);
        $DB->delete_records_list('tool_objectfs_report_data', 'reportid', $reportids);
    }

    /**
     * get_report_types
     * @return array
     */
    public static function get_report_types() {
        return [
            'location',
            'log_size',
            'mime_type',
            'tag_count',
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
        $reports = [];
        $records = $DB->get_records('tool_objectfs_reports', null, 'id DESC', 'id, reportdate');
        foreach ($records as $record) {
            $reports[$record->id] = $record->reportdate;
        }
        return $reports;
    }
}
