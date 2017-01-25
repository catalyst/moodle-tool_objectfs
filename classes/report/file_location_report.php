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
 * @package   tool_sssfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_sssfs\report;

defined('MOODLE_INTERNAL') || die();

class file_location_report extends sss_report {

    public function __construct() {
        $this->reporttype = SSSFS_REPORT_FILE_LOCATION;
    }

    public function calculate_report_data() {
        global $DB;
        $data = array();

        $sql = 'SELECT COALESCE(count(sub.contenthash) ,0) AS filecount,
                       COALESCE(SUM(sub.filesize) ,0) AS filesum
                  FROM (SELECT f.contenthash, MAX(f.filesize) AS filesize
                          FROM {files} f
                          JOIN {tool_sssfs_filestate} sf on f.contenthash = sf.contenthash
                          GROUP BY f.contenthash, f.filesize, sf.location
                          HAVING sf.location = ?) AS sub
                 WHERE sub.filesize != 0';

        $duplicate = $DB->get_records_sql($sql, array(SSS_FILE_LOCATION_DUPLICATED));
        $duplicate = reset($duplicate);
        $duplicate = self::create_report_data_record(SSSFS_REPORT_FILE_LOCATION, SSS_FILE_LOCATION_DUPLICATED, $duplicate->filecount, $duplicate->filesum);
        $data[SSS_FILE_LOCATION_DUPLICATED] = $duplicate;

        $external = $DB->get_records_sql($sql, array(SSS_FILE_LOCATION_EXTERNAL));
        $external = reset($external);
        $external = self::create_report_data_record(SSSFS_REPORT_FILE_LOCATION, SSS_FILE_LOCATION_EXTERNAL, $external->filecount, $external->filesum);
        $data[SSS_FILE_LOCATION_EXTERNAL] = $external;

        $sql = 'SELECT COALESCE(count(sub.contenthash) ,0) as filecount,
                       COALESCE(SUM(sub.filesize) ,0) as filesum
                        FROM (
                            SELECT F.contenthash, MAX(F.filesize) as filesize
                            FROM {files} F
                            GROUP BY F.contenthash, F.filesize
                        ) as sub
                        WHERE sub.filesize != 0';

        $local = $DB->get_records_sql($sql);
        $local = reset($local);

        $local->filecount -= $duplicate->filecount + $external->filecount;
        $local->filesum -= $duplicate->filesum + $external->filesum;

        $local = $this->create_report_data_record(SSSFS_REPORT_FILE_LOCATION, SSS_FILE_LOCATION_LOCAL, $local->filecount, $local->filesum);
        $data[SSS_FILE_LOCATION_LOCAL] = $local;

        return $data;
    }
}
