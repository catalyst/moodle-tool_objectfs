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

abstract class objectfs_report_builder {

    abstract public function build_report();

    public static function save_report_to_database(objectfs_report $report) {
        global $DB;
        $reporttype = $report->get_report_type();
        $reportrows = $report->get_rows();

        // Remove old records.
        $DB->delete_records('tool_objectfs_reports', array('reporttype' => $reporttype));

        // Add report type to each row.
        foreach ($reportrows as $row) {
            $row->reporttype = $reporttype;
            // We dont use insert_records because of 26 compatibility.
            $DB->insert_record('tool_objectfs_reports', $row);
        }
    }

    public static function load_report_from_database($reporttype) {
        global $DB;
        $rows = $DB->get_records('tool_objectfs_reports', array('reporttype' => $reporttype));
        $report = new objectfs_report($reporttype);
        $report->add_rows($rows);
        return $report;
    }

}
