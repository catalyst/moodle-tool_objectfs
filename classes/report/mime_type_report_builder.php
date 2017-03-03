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
 * Mime type report
 *
 * @package   tool_objectfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\report;

defined('MOODLE_INTERNAL') || die();

class mime_type_report_builder extends objectfs_report_builder {

    public function build_report() {
        global $DB;

        $report = new objectfs_report('mime_type');

        $sql = "SELECT sum(filesize) as objectsum, filetype as datakey, count(*) as objectcount
                FROM (SELECT distinct filesize,
                        CASE
                            WHEN mimetype = 'application/pdf'                                   THEN 'pdf'
                            WHEN mimetype = 'application/epub+zip'                              THEN 'epub'
                            WHEN mimetype = 'application/vnd.moodle.backup'                     THEN 'moodlebackup'
                            WHEN mimetype =    'application/msword'                             THEN 'document'
                            WHEN mimetype =    'application/x-mspublisher'                      THEN 'document'
                            WHEN mimetype like 'application/vnd.ms-word%'                       THEN 'document'
                            WHEN mimetype like 'application/vnd.oasis.opendocument.text%'       THEN 'document'
                            WHEN mimetype like 'application/vnd.openxmlformats-officedocument%' THEN 'document'
                            WHEN mimetype like 'application/vnd.ms-powerpoint%'                 THEN 'document'
                            WHEN mimetype = 'application/vnd.oasis.opendocument.presentation'   THEN 'document'
                            WHEN mimetype =    'application/vnd.oasis.opendocument.spreadsheet' THEN 'spreadsheet'
                            WHEN mimetype like 'application/vnd.ms-excel%'                      THEN 'spreadsheet'
                            WHEN mimetype =    'application/g-zip'                              THEN 'archive'
                            WHEN mimetype =    'application/x-7z-compressed'                    THEN 'archive'
                            WHEN mimetype =    'application/x-rar-compressed'                   THEN 'archive'
                            WHEN mimetype like 'application/%'                                  THEN 'other'
                            ELSE         substr(mimetype,0,position('/' IN mimetype))
                        END AS filetype
                        FROM {files}
                        WHERE mimetype IS NOT NULL) stats
                GROUP BY datakey
                ORDER BY
                sum(filesize) / 1024, datakey";

        $result = $DB->get_records_sql($sql);

        $report->add_rows($result);

        return $report;
    }
}
