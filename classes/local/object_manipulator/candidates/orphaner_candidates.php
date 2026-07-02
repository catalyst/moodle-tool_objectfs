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
class orphaner_candidates implements manipulator_candidates {
    /**
     * queryname
     * @var string
     */
    protected $queryname = 'get_orphan_candidates';

    /** @var \stdClass $config */
    protected $config;

    /**
     * orphaner_candidates constructor.
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
     * Get tracked objects that no longer have a reference in {files}.
     * Excludes objects already marked as orphaned (in_filedir=1, in_mdl_files=0, in_remote=0).
     *
     * @return array
     */
    public function get() {
        global $DB;
        $sql = 'SELECT o.id, o.contenthash, o.in_filedir, o.in_mdl_files, o.in_remote
                  FROM {tool_objectfs_objects} o
             LEFT JOIN {files} f ON o.contenthash = f.contenthash
                 WHERE f.id IS NULL
                   AND NOT (o.in_filedir = 1 AND o.in_mdl_files = 0 AND o.in_remote = 0)';
        return $DB->get_records_sql($sql, [], 0, $this->config->batchsize);
    }
}
