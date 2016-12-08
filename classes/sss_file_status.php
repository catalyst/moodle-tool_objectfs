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

namespace tool_sssfs;

class sss_file_status implements \renderable {

    public $statusdata;

    public function __construct () {
        $this->get_file_status();
    }

    public function get_file_status() {
        global $DB;

        $statusdata = array();

        // TODO: refactor constants so i dont have to do this.
        $filesystem = \tool_sssfs\sss_file_system::instance();

        $sql = 'SELECT count(*) as filecount, COALESCE(SUM(F.filesize),0) as filesum
                FROM {tool_sssfs_filestate} SFS
                JOIN {files} F ON F.contenthash=sfs.contenthash
                WHERE SFS.state = ?';

        $result = $DB->get_records_sql($sql, array(SSS_FILE_STATE_DUPLICATED));
        $statusdata['duplicate'] = reset($result);

        $result = $DB->get_records_sql($sql, array(SSS_FILE_STATE_EXTERNAL));
        $statusdata['s3'] = reset($result);

        $sql = 'SELECT count(DISTINCT contenthash) as filecount, SUM(filesize) as filesum from {files}';
        $result = $DB->get_records_sql($sql);
        $statusdata['disk'] = reset($result);

        $statusdata['disk']->filecount -= $statusdata['duplicate']->filecount + $statusdata['s3']->filecount;
        $statusdata['disk']->filesum -= $statusdata['duplicate']->filesum + $statusdata['s3']->filesum;

        $this->statusdata = $statusdata;
    }
}
