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

use tool_objectfs\local\object_manipulator\manipulator_builder;

define('OBJECTFS_PLUGIN_NAME', 'tool_objectfs');

/**
 * Location enum of the object
 * ORPHANED is when the {objectfs_objects} table contains a record linking to a
 * moodle {files} record which is no longer present.
 */
define('OBJECT_LOCATION_ORPHANED', -2);

/**
 * Location enum of the object
 * ERROR is when the file is missing when it is expected to be there.
 * @see tests/object_file_system_test.php for examples.
 */
define('OBJECT_LOCATION_ERROR', -1);

/**
 * Location enum of the object
 * LOCAL is when the object exists locally only.
 */
define('OBJECT_LOCATION_LOCAL', 0);

/**
 * Location enum of the object
 * DUPLICATED is when the object exists both locally, and remotely.
 */
define('OBJECT_LOCATION_DUPLICATED', 1);

/**
 * Location enum of the object
 * EXTERNAL is when when the object lives remotely only.
 */
define('OBJECT_LOCATION_EXTERNAL', 2);

define('OBJECTFS_REPORT_OBJECT_LOCATION', 0);
define('OBJECTFS_REPORT_LOG_SIZE', 1);
define('OBJECTFS_REPORT_MIME_TYPE', 2);

define('OBJECTFS_BYTES_IN_TERABYTE', 1099511627776);

define('TOOL_OBJECTFS_DELETE_EXTERNAL_NO', 0);
define('TOOL_OBJECTFS_DELETE_EXTERNAL_TRASH', 1);
define('TOOL_OBJECTFS_DELETE_EXTERNAL_FULL', 2);

// Legacy cron function.
function tool_objectfs_cron() {
    mtrace('RUNNING legacy cron objectfs');
    global $CFG;
    if ($CFG->branch <= 26) {
        // Unlike the task system, we do not get fine grained control over
        // when tasks/manipulators run. Every cron we just run all the manipulators.
        (new manipulator_builder())->execute_all();

        \tool_objectfs\local\report\objectfs_report::cleanup_reports();
        \tool_objectfs\local\report\objectfs_report::generate_status_report();
    }

    return true;
}

/**
 * Sends a plugin file to the browser.
 * @param $course
 * @param $cm
 * @param \context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 * @throws coding_exception
 */
function tool_objectfs_pluginfile($course, $cm, context $context, $filearea, array $args, bool $forcedownload,
    array $options = []) {

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, OBJECTFS_PLUGIN_NAME, $filearea, $args[0], '/', $args[1]);
    if (!$file || (is_object($file) && $file->is_directory())) {
        send_file_not_found();
    }
    $lifetime = optional_param('expires', null, PARAM_INT);
    \core\session\manager::write_close();
    send_stored_file($file, $lifetime, 0, $forcedownload, $options);
    return true;
}

/**
 * Get status checks for tool_objectfs.
 *
 * @return array
 */
function tool_objectfs_status_checks() {
    if (get_config('tool_objectfs', 'proxyrangerequests')) {
        return [
            new tool_objectfs\check\proxy_range_request()
        ];
    }

    return [];
}

/**
 * Get performance checks for tool_objectfs.
 *
 * @return array
 */
function tool_objectfs_performance_checks() {
    return [
        new \tool_objectfs\check\presigned_urls(),
    ];
}
