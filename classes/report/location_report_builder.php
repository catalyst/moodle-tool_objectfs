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

namespace tool_objectfs\report;

defined('MOODLE_INTERNAL') || die();

class location_report_builder extends objectfs_report_builder {

    public function build_report() {
        global $DB;
        $report = new objectfs_report('location');

        $locations = array(OBJECT_LOCATION_LOCAL,
                           OBJECT_LOCATION_DUPLICATED,
                           OBJECT_LOCATION_EXTERNAL,
                           OBJECT_LOCATION_ERROR);

        $totalcount = 0;
        $totalsum = 0;

        foreach ($locations as $location) {

            if ($location == OBJECT_LOCATION_LOCAL) {
                $localsql = ' or o.location IS NULL';
            } else {
                $localsql = '';
            }

            $sql = 'SELECT COALESCE(count(sub.contenthash) ,0) AS objectcount,
                           COALESCE(SUM(sub.filesize) ,0) AS objectsum
                      FROM (SELECT f.contenthash, MAX(f.filesize) AS filesize
                              FROM {files} f
                              LEFT JOIN {tool_objectfs_objects} o on f.contenthash = o.contenthash
                              GROUP BY f.contenthash, f.filesize, o.location
                              HAVING o.location = ?' . $localsql .') AS sub
                     WHERE sub.filesize > 0';

            $result = $DB->get_record_sql($sql, array($location));

            $result->datakey = $location;

            $report->add_row($result->datakey, $result->objectcount, $result->objectsum);

            $totalcount += $result->objectcount;
            $totalsum += $result->objectsum;
        }

        $report->add_row('total', $totalcount, $totalsum);

        return $report;
    }

}
