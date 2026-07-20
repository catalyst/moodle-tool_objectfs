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
 * Class checker_candidates
 * @package tool_objectfs
 * @author Gleimer Mora <gleimermora@catalyst-au.net>
 * @copyright Catalyst IT
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\local\object_manipulator\candidates;

/**
 * chcker_candiates
 */
class checker_candidates implements manipulator_candidates {
    /**
     * queryname
     * @var string
     */
    protected $queryname = 'get_check_candidates';

    /** @var \stdClass $config */
    protected $config;

    /**
     * checker_candidates constructor.
     * @param \stdClass $config
     */
    public function __construct(\stdClass $config) {
        $this->config = $config;
    }

    /**
     * get_query_name
     * @return string
     */
    public function get_query_name() {
        return $this->queryname;
    }

    /**
     * Get files that exist in {files} but either have no tracking row in {tool_objectfs_objects}
     * or have an ERROR state (all location bits zero), which includes rows migrated from a NULL
     * location in the previous schema.
     *
     * @return array
     */
    public function get() {
        global $DB;
        $errorconditions = \tool_objectfs\local\location_helper::bits_to_exact_sql(OBJECT_LOCATION_ERROR, 'o');
        $sql = "SELECT f.contenthash
                  FROM {files} f
             LEFT JOIN {tool_objectfs_objects} o ON f.contenthash = o.contenthash
                 WHERE f.filesize > 0
                   AND (o.id IS NULL OR ({$errorconditions}))
              GROUP BY f.contenthash";
        return $DB->get_records_sql($sql, [], 0, $this->config->batchsize);
    }
}
