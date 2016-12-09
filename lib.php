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

function save_config_data($data) {
    $config = new stdClass();
    $config->enabled = $data->enabled;
    $config->key = $data->key;
    $config->secret = $data->secret;
    $config->bucket = $data->bucket;
    $config->region = $data->region;
    $config->sizethreshold = $data->sizethreshold * 1024; // Convert from kb.
    $config->minimumage = $data->minimumage;
    $config->consistancydelay = $data->consistancydelay;
    $config->logginglocation = $data->logginglocation;

    // TODO: Encrypt credentials.

    foreach (get_object_vars($config) as $key => $value) {
        set_config($key, $value, 'tool_sssfs');
    }
}