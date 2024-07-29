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
 * Object location report builder.
 *
 * @package   tool_objectfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\local\report;

use tool_objectfs\local\manager;
use tool_objectfs\local\store\object_file_system;

/**
 * location_report_builder
 */
class location_report_builder extends objectfs_report_builder {

    /**
     * build_report
     * @param int $reportid
     * @return objectfs_report
     * @throws \dml_exception
     */
    public function build_report($reportid) {
        global $DB;
        $report = new objectfs_report('location', $reportid);
        $locations = [
            OBJECT_LOCATION_LOCAL,
            OBJECT_LOCATION_DUPLICATED,
            OBJECT_LOCATION_EXTERNAL,
            OBJECT_LOCATION_ORPHANED,
            OBJECT_LOCATION_ERROR,
        ];

        $totalcount = 0;
        $totalsum = 0;
        $filedircount = 0;
        $filedirsum = 0;
        foreach ($locations as $location) {
            $sql =
                'WITH
                  cte_objects AS (
                    SELECT o.contenthash, o.location
                      FROM {tool_objectfs_objects} o
                     WHERE o.location = ? ),
                  cte_obj_files AS (
                    SELECT f.contenthash, MAX(f.filesize) AS filesize
                      FROM {files} f
                INNER JOIN cte_objects co ON f.contenthash = co.contenthash
                     WHERE filesize > 0
                  GROUP BY f.contenthash, f.filesize)
               SELECT COALESCE(COUNT(cof.contenthash),0) AS objectcount,
                      COALESCE(SUM(cof.filesize),0) AS objectsum
                 FROM cte_obj_files cof';

            if ($location == OBJECT_LOCATION_LOCAL) {
                $sql =
                    'WITH
                      cte_objects AS (
                        SELECT o.contenthash,  o.location
                          FROM {tool_objectfs_objects} o ),
                      cte_obj_files AS (
                        SELECT f.contenthash, MAX(f.filesize) AS filesize
                          FROM {files} f
                     LEFT JOIN cte_objects co ON f.contenthash = co.contenthash
                         WHERE filesize > 0 AND ( co.location = ? OR co.location IS NULL )
                      GROUP BY f.contenthash, f.filesize)
                   SELECT COALESCE(COUNT(cof.contenthash),0) AS objectcount,
                          COALESCE(SUM(cof.filesize),0) AS objectsum
                     FROM cte_obj_files cof';
            }

            if ($location !== OBJECT_LOCATION_ORPHANED) {
                // Process the query normally.
                $result = $DB->get_record_sql($sql, [$location]);
            } else if ($location === OBJECT_LOCATION_ORPHANED) {
                // Start the query from objectfs, for ORPHANED objects, they are not located in the files table.
                $sql =
                    'WITH
                      cte_objects AS (
                        SELECT o.contenthash
                         FROM {tool_objectfs_objects} o
                        WHERE o.location = ?)
                   SELECT COALESCE(COUNT(co.contenthash),0) AS objectcount
                     FROM cte_objects co';
                $result = $DB->get_record_sql($sql, [$location]);
                $result->objectsum = 0;
            }

            $result->datakey = $location;

            $report->add_row($result->datakey, $result->objectcount, $result->objectsum);

            if (in_array($location, [OBJECT_LOCATION_LOCAL, OBJECT_LOCATION_DUPLICATED])) {
                $filedircount += $result->objectcount;
                $filedirsum += $result->objectsum;
            }
            $totalcount += $result->objectcount;
            $totalsum += $result->objectsum;
        }

        $report->add_row('total', $totalcount, $totalsum);
        $this->add_filedir_size_stats($report, $filedircount, $filedirsum);
        return $report;
    }

    /**
     * Update location report with the filedir size stats.
     * @param objectfs_report $report
     * @param int $totalcount
     * @param int $totalsum
     */
    private function add_filedir_size_stats(objectfs_report &$report, $totalcount, $totalsum) {
        $config = manager::get_objectfs_config();
        $rowcount = 0;
        $rowsum = 0;
        if (!empty($config->filesystem)) {
            /** @var object_file_system $filesystem */
            $filesystem = new $config->filesystem();

            $rowcount = $filesystem->get_filedir_count();
            $rowsum = $filesystem->get_filedir_size();
        }
        $key = 'deltaa';
        $report->add_row('filedir', $rowcount, $rowsum);
        $deltacount = $rowcount - $totalcount;
        $deltasize = $rowsum - $totalsum;
        if ($totalsum > $rowsum) {
            $key = 'deltab';
            $deltacount = $totalcount - $rowcount;
            $deltasize = $totalsum - $rowsum;
        }
        $report->add_row($key, $deltacount, $deltasize);
    }
}
