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
 * File location report.
 *
 * @package   tool_objectfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\report;

defined('MOODLE_INTERNAL') || die();

class object_location_report extends object_report {

    public function __construct() {
        $this->reporttype = OBJECTFS_REPORT_OBJECT_LOCATION;
    }

    public function calculate_report_data() {
        global $DB;
        $data = array();

        $sql = 'SELECT COALESCE(count(sub.contenthash) ,0) AS objectcount,
                       COALESCE(SUM(sub.filesize) ,0) AS objectsum
                  FROM (SELECT f.contenthash, MAX(f.filesize) AS filesize
                          FROM {files} f
                          JOIN {tool_objectfs_objects} sf on f.contenthash = sf.contenthash
                          GROUP BY f.contenthash, f.filesize, sf.location
                          HAVING sf.location = ?) AS sub
                 WHERE sub.filesize != 0';

        $error = $DB->get_records_sql($sql, array(OBJECT_LOCATION_ERROR));
        $error = reset($error);
        $error = self::create_report_data_record(OBJECTFS_REPORT_OBJECT_LOCATION, OBJECT_LOCATION_ERROR, $error->objectcount, $error->objectsum);
        $data[OBJECT_LOCATION_ERROR] = $error;

        $duplicate = $DB->get_records_sql($sql, array(OBJECT_LOCATION_DUPLICATED));
        $duplicate = reset($duplicate);
        $duplicate = self::create_report_data_record(OBJECTFS_REPORT_OBJECT_LOCATION, OBJECT_LOCATION_DUPLICATED, $duplicate->objectcount, $duplicate->objectsum);
        $data[OBJECT_LOCATION_DUPLICATED] = $duplicate;

        $external = $DB->get_records_sql($sql, array(OBJECT_LOCATION_REMOTE));
        $external = reset($external);
        $external = self::create_report_data_record(OBJECTFS_REPORT_OBJECT_LOCATION, OBJECT_LOCATION_REMOTE, $external->objectcount, $external->objectsum);
        $data[OBJECT_LOCATION_REMOTE] = $external;

        $sql = 'SELECT COALESCE(count(sub.contenthash) ,0) as objectcount,
                       COALESCE(SUM(sub.filesize) ,0) as objectsum
                        FROM (
                            SELECT F.contenthash, MAX(F.filesize) as filesize
                            FROM {files} F
                            GROUP BY F.contenthash, F.filesize
                        ) as sub
                        WHERE sub.filesize != 0';

        $local = $DB->get_records_sql($sql);
        $local = reset($local);

        $local->objectcount -= $error->objectcount + $duplicate->objectcount + $external->objectcount;
        $local->objectsum -= $error->objectsum + $duplicate->objectsum + $external->objectsum;

        $local = $this->create_report_data_record(OBJECTFS_REPORT_OBJECT_LOCATION, OBJECT_LOCATION_LOCAL, $local->objectcount, $local->objectsum);
        $data[OBJECT_LOCATION_LOCAL] = $local;

        return $data;
    }
}
