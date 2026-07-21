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
 * Class orphaner_candidates
 * @package tool_objectfs
 * @author Nathan Mares <ngmares@gmail.com>
 * @copyright Catalyst IT
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\local\object_manipulator\candidates;

/**
 * orphaner_candidates
 */
class orphaner_candidates extends manipulator_candidates_base {
    /**
     * queryname
     * @var string
     */
    protected $queryname = 'get_orphan_candidates';

    /**
     * Get tracked objects that no longer have a reference in {files}.
     *
     * @return array
     */
    public function get() {
        global $DB;
        $notorphaned = 'NOT (' . \tool_objectfs\local\location_helper::bits_to_exact_sql(OBJECT_LOCATION_ORPHANED, 'o') . ')';
        $sql = "SELECT o.id, o.contenthash
                  FROM {tool_objectfs_objects} o
             LEFT JOIN {files} f ON o.contenthash = f.contenthash
                 WHERE f.id is null
                   AND {$notorphaned}";
        $params = [];
        return $DB->get_records_sql($sql, $params, 0, $this->config->batchsize);
    }
}
