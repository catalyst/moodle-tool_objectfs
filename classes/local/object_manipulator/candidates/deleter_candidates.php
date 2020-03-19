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

defined('MOODLE_INTERNAL') || die();

class deleter_candidates extends manipulator_candidates_base {

    /** @var string $queryname */
    protected $queryname = 'get_delete_candidates';

    /**
     * @inheritDoc
     * @return string
     */
    public function get_candidates_sql() {
        return 'SELECT MAX(f.id),
                       f.contenthash,
                       MAX(f.filesize) AS filesize
                  FROM {files} f
                  JOIN {tool_objectfs_objects} o ON f.contenthash = o.contenthash
                 WHERE o.timeduplicated <= :consistancythreshold
                   AND o.location = :location
                   AND f.filesize > :sizethreshold
              GROUP BY f.contenthash,
                       f.filesize,
                       o.location';
    }

    /**
     * @inheritDoc
     * @return array
     */
    public function get_candidates_sql_params() {
        $consistancythreshold = time() - $this->config->consistencydelay;
        return [
            'consistancythreshold' => $consistancythreshold,
            'location' => OBJECT_LOCATION_DUPLICATED,
            'sizethreshold' => $this->config->sizethreshold
        ];
    }
}
