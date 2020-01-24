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
 * Sync location for files.
 *
 * @package   tool_objectfs
 * @author    Gleimer Mora <gleimermora@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\local\object_manipulator;

use stdClass;
use tool_objectfs\local\store\object_file_system;
use tool_objectfs\log\aggregate_logger;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/admin/tool/objectfs/lib.php');

class checker_filedir extends manipulator {

    /** @var bool $timeexceeded */
    public $timeexceeded = false;

    /**
     * sync_filedir constructor.
     * @param object_file_system $filesystem
     * @param $config
     * @param aggregate_logger $logger
     */
    public function __construct(object_file_system $filesystem, $config, aggregate_logger $logger) {
        parent::__construct($filesystem, $config);
        $this->logger = $logger;
        // Inject our logger into the filesystem.
        $this->filesystem->set_logger($this->logger);
    }

    /**
     * @return string
     */
    protected function get_query_name() {
        return '';
    }

    /**
     * @return string
     */
    protected function get_candidates_sql() {
        return '';
    }

    /**
     * @return array
     */
    protected function get_candidates_sql_params() {
        return [];
    }

    /**
     * @param stdClass $objectrecord
     * @return int
     */
    protected function manipulate_object($objectrecord) {
        return $this->filesystem->get_object_location_from_hash($objectrecord->contenthash);
    }

    /**
     * Set the las file name processed after exceeding the max exec time.
     * @param string $lastprocessed
     */
    public function set_last_processed($lastprocessed) {
        $this->timeexceeded = true;
        set_config('lastprocessed', $lastprocessed, 'tool_objectfs');
    }
}
