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

    private $data;

    public function __construct () {

    }

    private function load_data() {
        global $DB;
        $data = array();

        $records = $DB->get_records('tool_sssfs_file_status_data');

        if (count($records) === 0) {
            return false;
        }

        foreach ($records as $record) {
            $data[$record->state] = new \stdClass();
            $data[$record->state]->filecount = $record->filecount;
            $data[$record->state]->filesum = $record->filesum;
            $data[$record->state]->timecalculated = $record->timecalculated;
        }

        $this->data = $data;

        return true;
    }

    public function save_data() {
        global $DB;
        $timecalculated = time();
        foreach ($this->data as $filestate => $filestatedata) {

            $record = $DB->get_record('tool_sssfs_file_status_data', array('state' => $filestate));

            if (!$record) {
                $record = new \stdClass();
            }

            $record->state = $filestate;
            $record->filecount = $filestatedata->filecount;
            $record->filesum = $filestatedata->filesum;
            $record->timecalculated = $timecalculated;

            if (isset($record->id)) {
                $DB->update_record('tool_sssfs_file_status_data', $record);
            } else {
                $DB->insert_record('tool_sssfs_file_status_data', $record);
            }
        }
    }

    public function get_data() {
        if ($this->data || $this->load_data()) {
            return $this->data;
        }
        return false;
    }



    public function calculate_file_status() {
        global $DB;

        $data = array();

        $sql = 'SELECT COALESCE(count(sub.contenthash) ,0) as filecount,
                COALESCE(SUM(sub.filesize) ,0) as filesum
                FROM (
                    SELECT F.contenthash, MAX(F.filesize) as filesize
                    FROM {files} F
                    JOIN {tool_sssfs_filestate} SF on F.contenthash = SF.contenthash
                    GROUP BY F.contenthash, F.filesize, SF.state
                    HAVING SF.state = ?
                ) as sub';

        $result = $DB->get_records_sql($sql, array(SSS_FILE_STATE_DUPLICATED));
        $data[SSS_FILE_STATE_DUPLICATED] = reset($result);

        $result = $DB->get_records_sql($sql, array(SSS_FILE_STATE_EXTERNAL));
        $data[SSS_FILE_STATE_EXTERNAL] = reset($result);

        $sql = 'SELECT count(DISTINCT contenthash) as filecount, COALESCE(SUM(filesize) ,0) as filesum from {files}';
        $result = $DB->get_records_sql($sql);

        $data[SSS_FILE_STATE_LOCAL] = reset($result);
        $data[SSS_FILE_STATE_LOCAL]->filecount -= $data[SSS_FILE_STATE_DUPLICATED]->filecount + $data[SSS_FILE_STATE_EXTERNAL]->filecount;
        $data[SSS_FILE_STATE_LOCAL]->filesum -= $data[SSS_FILE_STATE_DUPLICATED]->filesum + $data[SSS_FILE_STATE_EXTERNAL]->filesum;

        $this->data = $data;

        return $data;
    }
}
