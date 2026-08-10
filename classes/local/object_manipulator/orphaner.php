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
 * Orphans records for files deleted
 *
 * Orphans {tool_objectfs_objects} records for files that have been
 * deleted from the core {files} table.
 *
 * @package   tool_objectfs
 * @author    Nathan Mares <ngmares@gmail.com>
 * @author    Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\local\object_manipulator;

use stdClass;
use tool_objectfs\local\location_helper;

/**
 * orphaner
 */
class orphaner extends manipulator {
    /**
     * Get query name for logging.
     * @return string
     */
    public static function get_query_name(): string {
        return 'get_orphan_candidates';
    }

    /**
     * Get tracked objects that no longer have a reference in {files}.
     * @param stdClass $config Plugin config.
     * @return array
     */
    public static function get_candidates(stdClass $config): array {
        global $DB;
        $notorphaned = 'NOT (' . location_helper::bits_to_exact_sql(OBJECT_LOCATION_ORPHANED, 'o') . ')';
        $sql = "SELECT o.id, o.contenthash
                  FROM {tool_objectfs_objects} o
             LEFT JOIN {files} f ON o.contenthash = f.contenthash
                 WHERE f.id is null
                   AND {$notorphaned}";
        return $DB->get_records_sql($sql, [], 0, $config->batchsize);
    }

    /**
     * Updates the location of {tool_objectfs_objects} records for files that
     * have been deleted from the core {files} table.
     *
     * @param \stdClass $objectrecord
     * @return int
     */
    public function manipulate_object(stdClass $objectrecord): int {
        return OBJECT_LOCATION_ORPHANED;
    }
}
