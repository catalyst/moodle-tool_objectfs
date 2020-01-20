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

use coding_exception;
use dml_exception;
use stdClass;
use tool_objectfs\local\store\object_file_system;
use tool_objectfs\log\aggregate_logger;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/admin/tool/objectfs/lib.php');

class checker_filedir extends manipulator {

    /**
     * @var array $totalfiles
     */
    private $totalfiles = [];

    /**
     * @var array $files
     */
    private $files = [];

    /**
     * sync_filedir constructor.
     * @param object_file_system $filesystem
     * @param $config
     * @param aggregate_logger $logger
     * @throws dml_exception
     */
    public function __construct(object_file_system $filesystem, $config, aggregate_logger $logger) {
        parent::__construct($filesystem, $config);
        $this->logger = $logger;
        // Inject our logger into the filesystem.
        $this->filesystem->set_logger($this->logger);
        $this->files = $this->filter_files();
    }

    /**
     * @return string
     */
    protected function get_query_name() {
        return 'get_checker_filedir_candidates';
    }

    /**
     * @return string
     * @throws coding_exception
     * @throws dml_exception
     */
    protected function get_candidates_sql() {
        global $DB;
        list($inclause, ) = $DB->get_in_or_equal($this->files);
        return 'SELECT id, contenthash
                FROM {tool_objectfs_objects}
                WHERE location > ?
                    AND contenthash ' . $inclause;
    }

    /**
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     */
    protected function get_candidates_sql_params() {
        global $DB;
        list(, $params) = $DB->get_in_or_equal($this->files);
        array_unshift($params, OBJECT_LOCATION_DUPLICATED);
        return $params;
    }

    /**
     * @param $objectrecord
     * @return int
     */
    protected function manipulate_object($objectrecord) {
        return OBJECT_LOCATION_DUPLICATED;
    }

    /**
     * Set the las file name processed or clean the records if all have been processed.
     * @param stdClass $lastprocessed
     * @throws dml_exception
     */
    protected function set_last_processed($lastprocessed) {
        $lastcontenthash = end($this->files);
        if (end($this->totalfiles) === $lastcontenthash) {
            set_config('lastprocessed', '', 'tool_objectfs');
            return;
        }
        $obj = new stdClass();
        $obj->contenthash = $lastcontenthash;
        if (!empty($lastprocessed->contenthash)) {
            $obj->contenthash = $lastprocessed->contenthash;
        }
        set_config('lastprocessed', $obj->contenthash, 'tool_objectfs');
    }

    /**
     * Get the file batch starting from the last processed file name and limited by the batchsize value.
     * @return array
     * @throws dml_exception
     */
    private function filter_files() {
        $lastprocessed = get_config('tool_objectfs', 'lastprocessed');
        $this->totalfiles = $this->filesystem->get_filenames_from_dir();
        $files = $this->totalfiles;
        if (!empty($lastprocessed)) {
            $files = array_filter($this->totalfiles, function ($name) use ($lastprocessed) {
                return $name > $lastprocessed ? true : false;
            });
        }
        return array_slice($files, 0, $this->batchsize);
    }
}
