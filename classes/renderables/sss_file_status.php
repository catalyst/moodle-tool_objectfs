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
 * File status renderable object.
 *
 * @package   tool_sssfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_sssfs\renderables;

defined('MOODLE_INTERNAL') || die();

class sss_file_status implements \renderable {

    private $reports;
    private $reportclasses;

    public function __construct () {
        $reportclasses = array('file_location_report',
                               'log_size_report',
                               'mime_type_report');

        foreach ($reportclasses as $reportclass) {
            $reportclass = "tool_sssfs\\report\\{$reportclass}";
            $report = new $reportclass();
            $reporttype = $report->get_type();
            $this->reports[$reporttype] = $report->get_report_data();
        }
    }

    public function get_report($reporttype) {
        return $this->reports[$reporttype];
    }




}
