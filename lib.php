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
 * S3 file system lib
 *
 * @package   tool_sssfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

define('SSS_FILE_STATE_LOCAL', 0);
define('SSS_FILE_STATE_DUPLICATED', 1);
define('SSS_FILE_STATE_EXTERNAL', 2);

function get_content_hashes_over_threshold($threshold) {
    global $DB;
    $sql = "SELECT DISTINCT contenthash FROM {files} WHERE filesize > ?";
    $contenthashes = $DB->get_fieldset_sql($sql, array($threshold));
    return $contenthashes;
}

function get_content_hashes_in_sss() {
    global $DB;
    $sql = 'SELECT contenthash FROM {tool_sssfs_filestate} WHERE STATE in (?, ?)';
    $ssscontenthashes = $DB->get_fieldset_sql($sql, array(SSS_FILE_STATE_DUPLICATED, SSS_FILE_STATE_EXTERNAL));
    return $ssscontenthashes;
}

function log_file_state($contenthash, $state) {
    global $DB;

    $logrecord = new \stdClass();
    $logrecord->contenthash = $contenthash;
    $logrecord->timeduplicated = time();
    $logrecord->state = $state;

    $existing = $DB->get_record('tool_sssfs_filestate', array('contenthash' => $contenthash));

    if ($existing) {
        $logrecord->id = $existing->id;
        $DB->update_record('tool_sssfs_filestate', $logrecord);

    } else {
        $DB->insert_record('tool_sssfs_filestate', $logrecord);
    }
}