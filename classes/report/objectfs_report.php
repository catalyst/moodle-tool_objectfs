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

namespace tool_objectfs\report;

defined('MOODLE_INTERNAL') || die();

class objectfs_report implements \renderable {
    protected $reporttype;
    protected $rows;

    public function __construct($reporttype) {
        $this->reporttype = $reporttype;
        $rows = array();
    }

    public function add_row($datakey, $objectcount, $objectsum) {
        $row = new \stdClass();
        $row->datakey = $datakey;
        $row->objectcount = $objectcount;
        $row->objectsum = $objectsum;
        $this->rows[] = $row;
    }

    public function add_rows($rows) {
        foreach ($rows as $row) {
            $this->add_row($row->datakey, $row->objectcount, $row->objectsum);
        }
    }

    public function get_rows() {
        return $this->rows;
    }

    public function get_report_type() {
        return $this->reporttype;
    }

    public static function get_last_generate_status_report_runtime() {
        global $DB, $CFG;

        if ($CFG->branch <= 26) {
            $lastruntime = $DB->get_field('config_plugins', 'value', array('name' => 'lastcron', 'plugin' => 'tool_objectfs'));
        } else {
            $lastruntime = $DB->get_field('task_scheduled', 'lastruntime', array('classname' => '\tool_objectfs\task\generate_status_report'));
        }

        return $lastruntime;
    }

    public static function generate_status_report() {
        $reporttypes = self::get_report_types();

        foreach ($reporttypes as $reporttype) {
            $reportbuilderclass = "tool_objectfs\\report\\{$reporttype}_report_builder";
            $reportbuilder = new $reportbuilderclass();
            $report = $reportbuilder->build_report();
            objectfs_report_builder::save_report_to_database($report);
        }
    }

    public static function get_report_types() {
        $reporttypes = array('location',
                              'log_size',
                              'mime_type');

        return $reporttypes;
    }
}
