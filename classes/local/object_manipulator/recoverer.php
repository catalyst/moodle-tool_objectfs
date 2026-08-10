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
 * Recovers objects that are in the error state if it can.
 *
 * @package   tool_objectfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\local\object_manipulator;

use stdClass;
use tool_objectfs\local\location_helper;

/**
 * recoverer
 */
class recoverer extends manipulator {
    /**
     * Get query name for logging.
     * @return string
     */
    public static function get_query_name(): string {
        return 'get_recover_candidates';
    }

    /**
     * Get candidate objects to recover from error state.
     * @param stdClass $config Plugin config.
     * @return array
     */
    public static function get_candidates(stdClass $config): array {
        global $DB;
        $locationconds = location_helper::bits_to_sql_conditions(
            OBJECT_LOCATION_IN_MDL_FILES,
            OBJECT_LOCATION_IN_FILEDIR | OBJECT_LOCATION_IN_REMOTE
        );
        $sql = "SELECT contenthash, filesize
                  FROM {tool_objectfs_objects}
                 WHERE {$locationconds}";
        return $DB->get_records_sql($sql, [], 0, $config->batchsize);
    }

    /**
     * manipulate_object
     * @param stdClass $objectrecord
     * @return int
     */
    public function manipulate_object(stdClass $objectrecord) {
        // The recoverer only knows the object is in the objectfs table, not necessarily
        // in mdl_files, so pass 0 to check all location bits fresh.
        return $this->filesystem->get_object_location_from_hash($objectrecord->contenthash, 0);
    }
}
