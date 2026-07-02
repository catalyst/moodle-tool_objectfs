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
 * Class deleter_candidates
 * @package tool_objectfs
 * @author Gleimer Mora <gleimermora@catalyst-au.net>
 * @copyright Catalyst IT
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\local\object_manipulator\candidates;

/**
 * deleter_candidates
 */
class deleter_candidates extends manipulator_candidates_base {
    /**
     * queryname
     * @var string
     */
    protected $queryname = 'get_delete_candidates';

    /**
     * get_candiates_sql
     * @return string
     */
    protected function get_candidates_sql(): string {
        $locationconditions = \tool_objectfs\local\location_helper::bits_to_exact_sql(OBJECT_LOCATION_DUPLICATED);
        return "SELECT contenthash,
                       filesize
                  FROM {tool_objectfs_objects}
                 WHERE timeduplicated <= :consistancythreshold
                   AND {$locationconditions}
                   AND filesize > :sizethreshold";
    }

    /**
     * get_candiates_sql_params
     * @return array
     */
    protected function get_candidates_sql_params(): array {
        $consistancythreshold = time() - $this->config->consistencydelay;
        return [
            'consistancythreshold' => $consistancythreshold,
            'sizethreshold' => $this->config->sizethreshold,
        ];
    }
}
