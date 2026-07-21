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
class checker_candidates extends manipulator_candidates_base {
    /**
     * queryname
     * @var string
     */
    protected $queryname = 'get_check_candidates';

    /**
     * Get files that exist in {files} but have no tracking row in {tool_objectfs_objects}.
     *
     * @return array
     */
    public function get() {
        global $DB;
        $sql = 'SELECT f.contenthash
                  FROM {files} f
             LEFT JOIN {tool_objectfs_objects} o ON f.contenthash = o.contenthash
                 WHERE f.filesize > 0
                   AND o.location is NULL
              GROUP BY f.contenthash';
        return $DB->get_records_sql($sql, [], 0, $this->config->batchsize);
    }
}
