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
define('OBJECT_LOCATION_EXTERNAL', 2);

define('OBJECTFS_REPORT_OBJECT_LOCATION', 0);
define('OBJECTFS_REPORT_LOG_SIZE', 1);
define('OBJECTFS_REPORT_MIME_TYPE', 2);

function update_object_record($contenthash, $location) {
    global $DB;

    $newobject = new \stdClass();
    $newobject->contenthash = $contenthash;
    $newobject->timeduplicated = time();
    $newobject->location = $location;

    $oldobject = $DB->get_record('tool_objectfs_objects', array('contenthash' => $contenthash));

    if ($oldobject) {

        // If location hasn't changed we do not need to update.
        if ($oldobject->location === $newobject->location) {
            return $oldobject;
        }

        // If location change is not to duplicated we do not update timeduplicated.
        if ($newobject->location !== OBJECT_LOCATION_DUPLICATED) {
            $newobject->timeduplicated = $oldobject->timeduplicated;
        }

        $newobject->id = $oldobject->id;

        $DB->update_record('tool_objectfs_objects', $newobject);
    } else {
        $DB->insert_record('tool_objectfs_objects', $newobject);
    }

    return $newobject;
}

function set_objectfs_config($config) {
    foreach ($config as $key => $value) {
        set_config($key, $value, 'tool_objectfs');
    }
}

function get_objectfs_config() {
    global $CFG;

    $config = new stdClass;
    $config->enabletasks = 0;
    $config->enablelogging = 0;
    $config->sizethreshold = 1024 * 10;
    $config->minimumage = 7 * 24 * 60 * 60;
    $config->deletelocal = 0;
    $config->consistencydelay = 10 * 60;
    $config->maxtaskruntime = 60;
    $config->logging = 0;
    $config->preferexternal = 0;

    $config->filesystem = '';

    // '\tool_objectfs\s3_file_system'
    $config->s3_key = '';
    $config->s3_secret = '';
    $config->s3_bucket = '';
    $config->s3_region = 'us-east-1';

    // '\tool_objectfs\azure_file_system'
    $config->azure_accountname = '';
    $config->azure_container = '';
    $config->azure_sastoken = '';

    $storedconfig = get_config('tool_objectfs');

    // Override defaults if set.
    foreach ($storedconfig as $key => $value) {
        $config->$key = $value;
    }
    return $config;
}

function tool_objectfs_get_client($config) {
    global $CFG;

    $fsclass = $CFG->alternative_file_system_class;

    $client = str_replace('file_system', 'client', $fsclass);
    $client = str_replace('\\tool_objectfs\\', '\\tool_objectfs\\client\\', $client);

    return new $client($config);
}

function tool_objectfs_get_client_components($type = 'base') {
    global $CFG;

    $found = [];

    $path = $CFG->dirroot . '/admin/tool/objectfs/classes/client/*_client.php';

    $clients = glob($path);

    foreach ($clients as $client) {
        $client = str_replace('_client.php', '', $client);
        $basename = basename($client);

        // Ignore the abstract class.
        if ($basename == 'object') {
            continue;
        }

        switch ($type) {
            case 'file_system':
                $found[$basename] = '\\tool_objectfs\\' . $basename . '_file_system';
                break;
            case 'client':
                $found[$basename] = '\\tool_objectfs\\client\\' . $basename . '_client';
                break;
            case 'base':
                $found[$basename] = $basename;
                break;
            default:
                break;
        }
    }

    return $found;
}

function tool_objectfs_should_tasks_run() {
    $config = get_objectfs_config();
    if (isset($config->enabletasks) && $config->enabletasks) {
        return true;
    }

    return false;
}

// Legacy cron function.
function tool_objectfs_cron() {
    mtrace('RUNNING legacy cron objectfs');
    global $CFG;
    if ($CFG->branch <= 26) {

        $manipulators = \tool_objectfs\object_manipulator\manipulator::get_all_manipulator_classnames();

        // Unlike the task system, we do not get fine grained control over
        // when tasks/manipulators run. Every cron we just run all the manipulators.
        foreach ($manipulators as $manipulator) {
            mtrace("Executing objectfs $manipulator");
            \tool_objectfs\object_manipulator\manipulator::setup_and_run_object_manipulator($manipulator);
            mtrace("Objectfs $manipulator successfully executed");
        }

        \tool_objectfs\report\objectfs_report::generate_status_report();
    }

    return true;
}
