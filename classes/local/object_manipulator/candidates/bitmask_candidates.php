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
 * Unified bitmask-based candidate class for object manipulation.
 *
 * Replaces separate pusher_candidates, puller_candidates, deleter_candidates,
 * and recoverer_candidates with a single parameterized class.
 *
 * @package   tool_objectfs
 * @author    Catalyst IT
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\local\object_manipulator\candidates;

use stdClass;
use tool_objectfs\local\location_helper;

/**
 * Universal candidate finder using bitmask filters on the location bit columns.
 *
 * Accepts two bitmasks:
 * - "has" mask: bits the file MUST have (in_filedir/in_mdl_files/in_remote column = 1)
 * - "not" mask: bits the file must NOT have (column = 0)
 *
 * Optional filters for filesize and timeduplicated are applied when provided.
 */
class bitmask_candidates extends manipulator_candidates_base {
    /** @var string Query name for logging. */
    protected $queryname;

    /** @var int Bits that must be set in location. */
    private $hasmask;

    /** @var int Bits that must NOT be set in location. */
    private $notmask;

    /** @var array Optional filter options. */
    private $options;

    /**
     * Constructor.
     *
     * @param stdClass $config Plugin config (must include batchsize).
     * @param int $hasmask Bits the location MUST have.
     * @param int $notmask Bits the location must NOT have.
     * @param string $queryname Name for logging.
     * @param array $options Optional filters:
     *   'threshold' => int    - filesize > threshold (minimum file size)
     *   'max_filesize' => int - filesize < max_filesize (maximum file size)
     *   'size_ceiling' => int - filesize <= size_ceiling (upper file size bound)
     *   'maxage' => int       - timeduplicated <= maxage (timestamp threshold)
     */
    public function __construct(stdClass $config, int $hasmask, int $notmask, string $queryname, array $options = []) {
        parent::__construct($config);
        $this->hasmask = $hasmask;
        $this->notmask = $notmask;
        $this->queryname = $queryname;
        $this->options = $options;
    }

    /**
     * Get candidate objects matching the bitmask filters.
     *
     * @return array
     */
    public function get() {
        global $DB;

        // Convert bitmasks to column-based SQL conditions.
        // Fall back to a tautology so the WHERE clause remains valid when no location filtering is requested.
        $locationconditions = location_helper::bits_to_sql_conditions($this->hasmask, $this->notmask) ?: '1=1';

        $conditions = [$locationconditions];
        $params = [];

        if (isset($this->options['threshold'])) {
            $conditions[] = 'filesize > :threshold';
            $params['threshold'] = $this->options['threshold'];
        }
        if (isset($this->options['max_filesize'])) {
            $conditions[] = 'filesize < :max_filesize';
            $params['max_filesize'] = $this->options['max_filesize'];
        }
        if (isset($this->options['size_ceiling'])) {
            $conditions[] = 'filesize <= :size_ceiling';
            $params['size_ceiling'] = $this->options['size_ceiling'];
        }
        if (isset($this->options['maxage'])) {
            $conditions[] = 'timeduplicated <= :maxage';
            $params['maxage'] = $this->options['maxage'];
        }

        $where = implode("\n                   AND ", $conditions);
        $sql = "SELECT contenthash,
                       filesize
                  FROM {tool_objectfs_objects}
                 WHERE {$where}";

        return $DB->get_records_sql($sql, $params, 0, $this->config->batchsize);
    }
}
