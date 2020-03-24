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

defined('MOODLE_INTERNAL') || die;

define('OBJECT_LOCATION_ERROR', -1);
define('OBJECT_LOCATION_LOCAL', 0);
define('OBJECT_LOCATION_DUPLICATED', 1);
define('OBJECT_LOCATION_EXTERNAL', 2);

define('OBJECTFS_REPORT_OBJECT_LOCATION', 0);
define('OBJECTFS_REPORT_LOG_SIZE', 1);
define('OBJECTFS_REPORT_MIME_TYPE', 2);

define('OBJECTFS_BYTES_IN_TERABYTE', 1099511627776);


/**
 * @param string $contenthash
 * @param int $newlocation
 * @return stdClass
 * @throws dml_exception
 */
function update_object_by_hash($contenthash, $newlocation) {
    global $DB;
    $newobject = new stdClass();
    $newobject->contenthash = $contenthash;

    $oldobject = $DB->get_record('tool_objectfs_objects', ['contenthash' => $contenthash]);
    if ($oldobject) {
        $newobject->timeduplicated = $oldobject->timeduplicated;
        $newobject->id = $oldobject->id;

        // If location hasn't changed we do not need to update.
        if ((int)$oldobject->location === $newlocation) {
            return $oldobject;
        }

        return update_object($newobject, $newlocation);
    }
    $newobject->timeduplicated = time();
    $newobject->location = $newlocation;
    $DB->insert_record('tool_objectfs_objects', $newobject);

    return $newobject;
}

/**
 * @param stdClass $object
 * @param int $newlocation
 * @return stdClass
 * @throws dml_exception
 */
function update_object(stdClass $object, $newlocation) {
    global $DB;

    // If location change is 'duplicated' we update timeduplicated.
    if ($newlocation === OBJECT_LOCATION_DUPLICATED) {
        $object->timeduplicated = time();
    }

    $object->location = $newlocation;
    $DB->update_record('tool_objectfs_objects', $object);

    return $object;
}

// Legacy cron function.
function tool_objectfs_cron() {
    mtrace('RUNNING legacy cron objectfs');
    global $CFG;
    if ($CFG->branch <= 26) {
        // Unlike the task system, we do not get fine grained control over
        // when tasks/manipulators run. Every cron we just run all the manipulators.
        (new manipulator_builder())->execute_all();

        \tool_objectfs\local\report\objectfs_report::generate_status_report();
    }

    return true;
}
