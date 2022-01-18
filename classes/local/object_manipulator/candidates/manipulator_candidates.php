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
 * Interface manipulator_candidates
 * @package tool_objectfs
 * @author Gleimer Mora <gleimermora@catalyst-au.net>
 * @copyright Catalyst IT
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\local\object_manipulator\candidates;

use dml_exception;

interface manipulator_candidates {

    /**
     * Returns a manipulator query name for logging.
     *
     * @return string
     */
    public function get_query_name();

    /**
     * Returns SQL to retrieve objects for manipulation.
     *
     * @return string
     */
    public function get_candidates_sql();

    /**
     * Returns a list of parameters for SQL from get_candidates_sql.
     *
     * @return array
     */
    public function get_candidates_sql_params();

    /**
     * @return array
     * @throws dml_exception
     */
    public function get();
}
