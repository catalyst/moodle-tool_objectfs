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
 * @package   tool_objectfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\local\report;

/**
 * log_size_report_builder
 */
class log_size_report_builder extends objectfs_report_builder {

    /**
     * build_report
     * @param int $reportid
     *
     * @return objectfs_report
     */
    public function build_report($reportid) {
        global $DB;

        $report = new objectfs_report('log_size', $reportid);

        $sql = 'SELECT floor (log(2, filesize)) as datakey,
                       sum(filesize) as objectsum,
                       count(*) as objectcount
                  FROM (SELECT DISTINCT contenthash, filesize
                            FROM {files}
                            WHERE filesize > 0) d
              GROUP BY datakey ORDER BY datakey';

        $stats = $DB->get_records_sql($sql);

        $this->compress_small_log_sizes($stats);

        $report->add_rows($stats);

        return $report;
    }

    /**
     * compress_small_log_sizes
     * @param mixed $stats
     *
     * @return void
     */
    public function compress_small_log_sizes(&$stats) {
        $smallstats = new \stdClass();
        $smallstats->datakey = 1;
        $smallstats->objectsum = 0;
        $smallstats->objectcount = 0;

        foreach ($stats as $key => $stat) {

            // Logsize of <= 9 means that files are smaller than 1 KB.
            if ($stat->datakey <= 9) {
                $smallstats->objectcount += $stat->objectcount;
                $smallstats->objectsum += $stat->objectsum;
                unset($stats[$key]);
            }

        }
        // Add to the beginning of the array.
        array_unshift($stats, $smallstats);
    }
}
