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

define('OBJECTFS_BYTES_IN_TERABYTE', 1099511627776);

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
    $config = new stdClass;
    $config->enabletasks = 0;
    $config->enablelogging = 0;
    $config->sizethreshold = 1024 * 10;
    $config->minimumage = 7 * DAYSECS;
    $config->deletelocal = 0;
    $config->consistencydelay = 10 * MINSECS;
    $config->maxtaskruntime = MINSECS;
    $config->logging = 0;
    $config->preferexternal = 0;
    $config->batchsize = 10000;

    $config->filesystem = '';
    $config->enablepresignedurls = 0;
    $config->expirationtime = 10 * MINSECS;
    $config->presignedminfilesize = 0;

    // S3 file system.
    $config->s3_key = '';
    $config->s3_secret = '';
    $config->s3_bucket = '';
    $config->s3_region = 'us-east-1';

    // Digital ocean file system.
    $config->do_key = '';
    $config->do_secret = '';
    $config->do_space = '';
    $config->do_region = 'sfo2';

    // Azure file system.
    $config->azure_accountname = '';
    $config->azure_container = '';
    $config->azure_sastoken = '';

    // Cloudfront CDN with Signed URLS - canned policy.
    $config->cloudfront_resource_domain = '';
    $config->cloudfront_key_pair_id = '';
    $config->cloudfront_private_key_pem_file_pathname = '';

    // Cloudfront CDN with Signed URLS - custom policy (optional - advanced usage).
    $config->cloudfront_custom_policy_json = '';

    // SigningMethod - determine whether S3 or Cloudfront etc should be used.
    $config->signingmethod = 'S3';  // This will be the default if not otherwise set. Values ('S3' | 'CF').

    $storedconfig = get_config('tool_objectfs');

    // Override defaults if set.
    foreach ($storedconfig as $key => $value) {
        $config->$key = $value;
    }
    return $config;
}

function tool_objectfs_get_client($config) {
    $fsclass = $config->filesystem;
    $client = str_replace('_file_system', '', $fsclass);
    $client = str_replace('tool_objectfs\\', 'tool_objectfs\\local\\store\\', $client.'\\client');

    if (class_exists($client)) {
        return new $client($config);
    }

    return false;
}

function tool_objectfs_get_fs_list() {
    $found[''] = 'Please, select';
    $found['\tool_objectfs\azure_file_system'] = '\tool_objectfs\azure_file_system';
    $found['\tool_objectfs\digitalocean_file_system'] = '\tool_objectfs\digitalocean_file_system';
    $found['\tool_objectfs\s3_file_system'] = '\tool_objectfs\s3_file_system';
    $found['\tool_objectfs\swift_file_system'] = '\tool_objectfs\swift_file_system';
    return $found;
}

function tool_objectfs_should_tasks_run() {
    $config = get_objectfs_config();
    if (isset($config->enabletasks) && $config->enabletasks) {
        return true;
    }

    return false;
}

function tool_objectfs_filesystem_supports_presigned_urls($fs) {
    $supportedlist = array();
    $supportedlist[] = '\tool_objectfs\s3_file_system';
    if (in_array($fs, $supportedlist)) {
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

        \tool_objectfs\local\report\objectfs_report::generate_status_report();
    }

    return true;
}

function tool_objectfs_get_signingmethod_list() {
    $availablemethods = array(''=>'S3', 'CF'=>'Cloudfront');
    return $availablemethods;
}
