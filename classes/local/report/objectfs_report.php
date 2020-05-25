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

    /** @var int $reportstarted */
    protected $reportstarted = 0;

    /** @var array $rows */
    protected $rows = [];

    /**
     * @param string $reporttype
     */
    public function __construct($reporttype, $reportstarted) {
        $this->reporttype = $reporttype;
        $this->reportstarted = $reportstarted;
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
    public function get_report_started() {
        return $this->reportstarted;
    }

    /**
     * @return mixed
     * @throws \dml_exception
     */
    public static function get_last_generate_status_report_runtime() {
        global $DB, $CFG;

        if ($CFG->branch <= 26) {
            $lastruntime = $DB->get_field('config_plugins', 'value',
                array('name' => 'lastcron', 'plugin' => 'tool_objectfs'));
        } else {
            $lastruntime = $DB->get_field('task_scheduled', 'lastruntime',
                array('classname' => '\tool_objectfs\task\generate_status_report'));
        }

        return $lastruntime;
    }

    public static function generate_status_report() {
        $reportstarted = time();
        $reporttypes = self::get_report_types();

        foreach ($reporttypes as $reporttype) {
            $reportbuilderclass = "tool_objectfs\\local\\report\\{$reporttype}_report_builder";
            $reportbuilder = new $reportbuilderclass();
            $report = $reportbuilder->build_report($reportstarted);
            objectfs_report_builder::save_report_to_database($report);
        }
        // Throttle here for one second to make sure the snapshots have different
        // $reportstarted if the report was called twice in a row and it was super fast.
        sleep(1);
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
    public static function get_report_dates() {
        global $DB;
        $dates = array();
        $sql = 'SELECT DISTINCT timecreated
                  FROM {tool_objectfs_reports}
              ORDER BY timecreated DESC';
        $reports = $DB->get_records_sql($sql, null, 0, 100);

        foreach ($reports as $report) {
            $dates[$report->timecreated] = userdate($report->timecreated, get_string('strftimedaydatetime'));
        }

        return $dates;
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
                $table->data[$i][$col] = sprintf('<div class="ofs-bar" style="width:%.1f%%">%s</div>',
                    100 * $row[$col] / $max,
                    $col == 1 ? number_format($row[$col]) : display_size($row[$col])
                );
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
