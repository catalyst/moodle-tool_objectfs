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
use tool_objectfs\local\tag\tag_manager;

define('OBJECTFS_PLUGIN_NAME', 'tool_objectfs');

/**
 * Location bit flag: the object exists in the local filedir.
 * Bit 1.
 */
define('OBJECT_LOCATION_IN_FILEDIR', 1);

/**
 * Location bit flag: the object is referenced in the Moodle files table (mdl_files).
 * Bit 2.
 */
define('OBJECT_LOCATION_IN_MDL_FILES', 2);

/**
 * Location bit flag: the object exists in the primary remote object store.
 * Bit 4.
 */
define('OBJECT_LOCATION_IN_REMOTE', 4);

/**
 * Location of the object: completely absent — no bits set.
 * This is an invalid state that should not normally be stored in the database.
 * Value: 0.
 */
define('OBJECT_LOCATION_ERROR', 0);

/**
 * Location of the object: in filedir only, no mdl_files reference.
 * This is a trashdir candidate marked for cleanup.
 * Value: OBJECT_LOCATION_IN_FILEDIR = 1.
 */
define('OBJECT_LOCATION_ORPHANED', OBJECT_LOCATION_IN_FILEDIR);

/**
 * Location of the object: referenced in mdl_files but not present in filedir or any remote store.
 * This is a missing-file error state.
 * Value: OBJECT_LOCATION_IN_MDL_FILES = 2.
 * @see tests/object_file_system_test.php for examples.
 */
define('OBJECT_LOCATION_MISSING', OBJECT_LOCATION_IN_MDL_FILES);

/**
 * Location of the object: in filedir and mdl_files, not yet pushed to any remote store.
 * Value: OBJECT_LOCATION_IN_FILEDIR | OBJECT_LOCATION_IN_MDL_FILES = 3.
 */
define('OBJECT_LOCATION_LOCAL', OBJECT_LOCATION_IN_FILEDIR | OBJECT_LOCATION_IN_MDL_FILES);

/**
 * Location of the object: in mdl_files and the primary remote store, no local filedir copy.
 * Value: OBJECT_LOCATION_IN_MDL_FILES | OBJECT_LOCATION_IN_REMOTE = 6.
 */
define('OBJECT_LOCATION_EXTERNAL', OBJECT_LOCATION_IN_MDL_FILES | OBJECT_LOCATION_IN_REMOTE);

/**
 * Location of the object: in filedir, mdl_files, and the primary remote store.
 * Value: OBJECT_LOCATION_IN_FILEDIR | OBJECT_LOCATION_IN_MDL_FILES | OBJECT_LOCATION_IN_REMOTE = 7.
 */
define('OBJECT_LOCATION_DUPLICATED', OBJECT_LOCATION_IN_FILEDIR | OBJECT_LOCATION_IN_MDL_FILES | OBJECT_LOCATION_IN_REMOTE);

define('OBJECTFS_REPORT_OBJECT_LOCATION', 0);
define('OBJECTFS_REPORT_LOG_SIZE', 1);
define('OBJECTFS_REPORT_MIME_TYPE', 2);

define('OBJECTFS_BYTES_IN_TERABYTE', 1099511627776);

define('TOOL_OBJECTFS_DELETE_EXTERNAL_NO', 0);
define('TOOL_OBJECTFS_DELETE_EXTERNAL_TRASH', 1);
define('TOOL_OBJECTFS_DELETE_EXTERNAL_FULL', 2);

/**
 * Sends a plugin file to the browser.
 * @param mixed $course
 * @param mixed $cm
 * @param \context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 * @throws coding_exception
 */
function tool_objectfs_pluginfile(
    $course,
    $cm,
    context $context,
    $filearea,
    array $args,
    bool $forcedownload,
    array $options = []
) {

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
    $checks = [
        new tool_objectfs\check\token_expiry(),
        new tool_objectfs\check\connection(),
    ];
    if (get_config('tool_objectfs', 'taggingenabled') == '1') {
        $checks += [
            new tool_objectfs\check\tagging_status(),
            new tool_objectfs\check\tagging_sync_status(),
            new tool_objectfs\check\tagging_migration_status(),
        ];
    }

    if (get_config('tool_objectfs', 'proxyrangerequests')) {
        $checks[] = new tool_objectfs\check\proxy_range_request();
    }

    return $checks;
}
