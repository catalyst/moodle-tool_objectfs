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
 * @package   tool_objectfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

define('OBJECT_LOCATION_ERROR', -1);
define('OBJECT_LOCATION_LOCAL', 0);
define('OBJECT_LOCATION_DUPLICATED', 1);
define('OBJECT_LOCATION_REMOTE', 2);

define('OBJECTFS_REPORT_OBJECT_LOCATION', 0);
define('OBJECTFS_REPORT_LOG_SIZE', 1);
define('OBJECTFS_REPORT_MIME_TYPE', 2);

function update_object_record($contenthash, $location, $md5 = false) {
    global $DB;

    $logrecord = new \stdClass();
    $logrecord->contenthash = $contenthash;
    $logrecord->timeduplicated = time();
    $logrecord->location = $location;

    $existing = $DB->get_record('tool_objectfs_objects', array('contenthash' => $contenthash));

    if ($md5) {
        $logrecord->md5 = $md5;
    } else if ($existing) {
        $logrecord->md5 = $existing->md5;
    }

    if ($existing) {
        $logrecord->id = $existing->id;
        $DB->update_record('tool_objectfs_objects', $logrecord);
    } else {
        $DB->insert_record('tool_objectfs_objects', $logrecord);
    }
}

function set_objectfs_config($config) {
    foreach ($config as $key => $value) {
        set_config($key, $value, 'tool_objectfs');
    }
}

function get_objectfs_config() {
    $config = array(  'enabled'           => 0,
                      'key'               => '',
                      'secret'            => '',
                      'bucket'            => '',
                      'region'            => 'us-east-1',
                      'sizethreshold'     => 1024 * 10,
                      'minimumage'        => 7 * 24 * 60 * 60,
                      'deletelocal'       => 0,
                      'consistencydelay'  => 10 * 60,
                      'maxtaskruntime'    => 60,
                      'logging'           => 0,
                      'preferremote'         => 0);

    $storedconfig = get_config('tool_objectfs');

    // Override defaults if set.
    foreach ($storedconfig as $key => $value) {
        $config[$key] = $value;
    }
    return $config;
}

function get_object_location_from_hash($contenthash) {
    global $DB;
    $location = $DB->get_field('tool_objectfs_objects', 'location', array('contenthash' => $contenthash));
    return $location;
}