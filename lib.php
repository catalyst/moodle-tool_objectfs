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

function update_object_record($contenthash, $location) {
    global $DB;

    $logrecord = new \stdClass();
    $logrecord->contenthash = $contenthash;
    $logrecord->timeduplicated = time();
    $logrecord->location = $location;

    $existing = $DB->get_record('tool_objectfs_objects', array('contenthash' => $contenthash));

    if ($existing) {

        // If state change is not to duplicated we do not update timeduplicated.
        if ($location !== OBJECT_LOCATION_DUPLICATED) {
            $logrecord->timeduplicated = $existing->timeduplicated;
        }

        $logrecord->id = $existing->id;
        $DB->update_record('tool_objectfs_objects', $logrecord);
    } else {
        $DB->insert_record('tool_objectfs_objects', $logrecord);
    }

    return $logrecord;
}

function set_objectfs_config($config) {
    foreach ($config as $key => $value) {
        set_config($key, $value, 'tool_objectfs');
    }
}

function get_objectfs_config() {
    $config = new stdClass;
    $config->enabletasks = 0;
    $config->key = '';
    $config->secret = '';
    $config->bucket = '';
    $config->region = 'us-east-1';
    $config->sizethreshold = 1024 * 10;
    $config->minimumage = 7 * 24 * 60 * 60;
    $config->deletelocal = 0;
    $config->consistencydelay = 10 * 60;
    $config->maxtaskruntime = 60;
    $config->logging = 0;
    $config->preferremote = 0;

    $storedconfig = get_config('tool_objectfs');

    // Override defaults if set.
    foreach ($storedconfig as $key => $value) {
        $config->$key = $value;
    }
    return $config;
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

        $tasksshouldrun = tool_objectfs_should_tasks_run();

        if (!$tasksshouldrun) {
            mtrace('Objectfs tasks not configured to run, exiting');
            return true;
        }

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