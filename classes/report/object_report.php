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
 * sss report abstract class.
 *
 * @package   tool_objectfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\report;

defined('MOODLE_INTERNAL') || die();

abstract class object_report {
    protected $reporttype;

    public function __construct() {

    }

    public static function get_last_task_runtime() {
        global $DB;
        $lastruntime = $DB->get_field('task_scheduled', 'lastruntime', array('classname' => '\tool_objectfs\task\generate_status_report'));
        return $lastruntime;
    }

    protected function create_report_data_record($reporttype, $datakey, $objectcount, $objectsum) {
        $record = new \stdClass();
        $record->reporttype = $reporttype;
        $record->datakey = $datakey;
        $record->objectcount = $objectcount;
        $record->objectsum = $objectsum;
        return $record;
    }

    public function save_report_data($reportdata) {
        global $DB;
        $DB->delete_records('tool_objectfs_report_data', array('reporttype' => $this->reporttype)); // Clear out old records.
        $DB->insert_records('tool_objectfs_report_data', $reportdata);
    }

    public function get_report_data() {
        global $DB;
        $data = $DB->get_records('tool_objectfs_report_data', array('reporttype' => $this->reporttype));
        return $data;
    }

    public function get_type() {
        return $this->reporttype;
    }

    abstract public function calculate_report_data();
}
