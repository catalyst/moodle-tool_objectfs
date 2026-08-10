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
 * Checks files to have their location stored in database.
 *
 * @package   tool_objectfs
 * @author    Mikhail Golenkov <mikhailgolenkov@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\local\object_manipulator;

use stdClass;
use tool_objectfs\local\location_helper;
use tool_objectfs\local\store\object_file_system;
use tool_objectfs\log\aggregate_logger;

/**
 * checker
 */
class checker extends manipulator {
    /**
     * Get query name for logging.
     * @return string
     */
    public static function get_query_name(): string {
        return 'get_check_candidates';
    }

    /**
     * Get files that exist in {files} but have no tracking row or are in error state.
     * @param stdClass $config Plugin config.
     * @return array
     */
    public static function get_candidates(stdClass $config): array {
        global $DB;
        $errorconditions = location_helper::bits_to_exact_sql(OBJECT_LOCATION_ERROR, 'o');
        $sql = "SELECT f.contenthash
                  FROM {files} f
             LEFT JOIN {tool_objectfs_objects} o ON f.contenthash = o.contenthash
                 WHERE f.filesize > 0
                   AND (o.id IS NULL OR ({$errorconditions}))
              GROUP BY f.contenthash";
        return $DB->get_records_sql($sql, [], 0, $config->batchsize * 10);
    }

    /**
     * Checker constructor.
     * This manipulator adds location for files that do not have records in {tool_objectfs_objects} table.
     *
     * @param object_file_system $filesystem objectfs file system
     * @param stdClass $config objectfs config.
     * @param aggregate_logger $logger
     */
    public function __construct(object_file_system $filesystem, stdClass $config, aggregate_logger $logger) {
        parent::__construct($filesystem, $config, $logger);
        $this->batchsize = $this->batchsize * 10;
    }

    /**
     * manipulate_object
     * @param stdClass $objectrecord
     * @return int
     */
    public function manipulate_object(stdClass $objectrecord) {
        return $this->filesystem->get_object_location_from_hash($objectrecord->contenthash);
    }
}
