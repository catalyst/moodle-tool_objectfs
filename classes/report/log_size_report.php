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
 * Log size report
 *
 * @package   tool_sssfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_sssfs\report;

defined('MOODLE_INTERNAL') || die();

class log_size_report extends sss_report {

    public function __construct() {
        $this->reporttype = SSSFS_REPORT_LOG_SIZE;
    }

    public function calculate_report_data() {
        global $DB;

        $data = array();

        $sql = 'SELECT log logindex, sum(filesize) filesum, count(*) filecount from
                    (SELECT distinct contenthash, filesize, floor(log(2,filesize) * 4) as log
                        from {files}
                        where filesize != 0
                    ) d
                group by log order by log';

        $logdata = $DB->get_records_sql($sql);

        foreach ($logdata as $record) {
            $data[$record->logindex] = $this->create_report_data_record(SSSFS_REPORT_LOG_SIZE, $record->logindex, $record->filecount, $record->filesum);
        }

        return $data;
    }
}
