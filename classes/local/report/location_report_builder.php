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

use tool_objectfs\local\location_query;
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
        $report = new objectfs_report('location', $reportid);
        $locations = [
            OBJECT_LOCATION_LOCAL,
            OBJECT_LOCATION_DUPLICATED,
            OBJECT_LOCATION_EXTERNAL,
            OBJECT_LOCATION_ORPHANED,
            OBJECT_LOCATION_ERROR,
        ];

        $results = location_query::uses_bits()
            ? $this->get_results_from_bits($locations)
            : $this->get_results_from_legacy_location($locations);

        $totalcount = 0;
        $totalsum = 0;
        $filedircount = 0;
        $filedirsum = 0;
        foreach ($locations as $location) {
            $result = $results[$location];

            $report->add_row($location, $result->objectcount, $result->objectsum);

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
     * Counts and sizes per location from the objects table only, without joining {files}.
     *
     * Files with no object row yet are not included until the checker has created their row.
     *
     * @param int[] $locations OBJECT_LOCATION_* constants.
     * @return \stdClass[] Keyed by location, each with objectcount and objectsum.
     */
    private function get_results_from_bits(array $locations): array {
        global $DB;

        $results = [];
        $keys = [];
        foreach ($locations as $location) {
            $results[$location] = (object) ['objectcount' => 0, 'objectsum' => 0];
            $keys[implode(':', location_query::bits($location))] = $location;
        }

        // Zero-size files are excluded, matching the legacy report.
        $sql = 'SELECT o.in_filedir, o.in_mdl_files, o.in_remote,
                       COUNT(1) AS objectcount,
                       COALESCE(SUM(o.filesize), 0) AS objectsum
                  FROM {tool_objectfs_objects} o
                 WHERE o.in_mdl_files = 0 OR o.filesize IS NULL OR o.filesize > 0
              GROUP BY o.in_filedir, o.in_mdl_files, o.in_remote';
        $rows = $DB->get_recordset_sql($sql);
        foreach ($rows as $row) {
            $location = $keys["{$row->in_filedir}:{$row->in_mdl_files}:{$row->in_remote}"] ?? null;
            if ($location === null) {
                continue;
            }
            $results[$location]->objectcount = (int) $row->objectcount;
            // Orphaned objects have no file in {files}, so no size is reported.
            $results[$location]->objectsum = $location === OBJECT_LOCATION_ORPHANED ? 0 : (int) $row->objectsum;
        }
        $rows->close();

        return $results;
    }

    /**
     * Per-location queries joining {files}, used until the location bits migration completes.
     *
     * @param int[] $locations OBJECT_LOCATION_* constants.
     * @return \stdClass[] Keyed by location, each with objectcount and objectsum.
     */
    private function get_results_from_legacy_location(array $locations): array {
        global $DB;

        $results = [];
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
            } else {
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

            $results[$location] = $result;
        }

        return $results;
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
