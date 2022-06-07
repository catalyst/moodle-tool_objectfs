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

class checker_candidates extends  manipulator_candidates_base {

    /** @var string $queryname */
    protected $queryname = 'get_check_candidates';

    /**
     * @inheritDoc
     * @return string
     */
    public function get_candidates_sql() {
        return 'SELECT f.contenthash
                  FROM {files} f
             LEFT JOIN {tool_objectfs_objects} o ON f.contenthash = o.contenthash
                 WHERE f.filesize > 0
                   AND o.location is NULL
              GROUP BY f.contenthash';
    }

    /**
     * @inheritDoc
     * @return array
     */
    public function get_candidates_sql_params() {
        return [];
    }
}
