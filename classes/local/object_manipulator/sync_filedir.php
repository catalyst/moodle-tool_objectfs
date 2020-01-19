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

class sync_filedir extends manipulator {

    /**
     * @var array $totalfiles
     */
    private $totalfiles = [];

    /**
     * @var array $files
     */
    private $files = [];

    private $firstrun = true;

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
        return 'get_sync_filedir_candidates';
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
        global $DB;
        $table = 'tool_objectfs_sync_location';
        $lastcontenthash = end($this->files);
        if (end($this->totalfiles) === $lastcontenthash) {
            $DB->delete_records_select($table, 'id > 0');
            return;
        }
        $obj = new stdClass();
        $obj->id = $this->firstrun;
        $obj->contenthash = $lastcontenthash;
        if (!empty($lastprocessed->contenthash)) {
            $obj->contenthash = $lastprocessed->contenthash;
            $obj->id = $lastprocessed->id;
        }
        if ($this->firstrun === true) {
            $DB->insert_record($table, $obj);
        } else {
            $DB->update_record($table, $obj);
        }
    }

    /**
     * Get the file batch starting from the last processed file name and limited by the batchsize value.
     * @return array
     * @throws dml_exception
     */
    private function filter_files() {
        global $DB;
        $sql = 'SELECT id, contenthash FROM {tool_objectfs_sync_location}';
        $result = $DB->get_records_sql($sql);
        $this->totalfiles = $this->filesystem->get_filenames_from_dir();
        $files = $this->totalfiles;
        if (!empty($result)) {
            $obj = end($result);
            $this->firstrun = $obj->id;
            $lastprocessed = $obj->contenthash;
            $files = array_filter($this->totalfiles, function ($name) use ($lastprocessed) {
                return $name > $lastprocessed ? true : false;
            });
        }
        return array_slice($files, 0, $this->batchsize);
    }
}
