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
 * Pulls files from remote storage if they meet the configured criterea.
 *
 * @package   tool_objectfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\local\object_manipulator;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/admin/tool/objectfs/lib.php');

class puller extends manipulator {

    /**
     * Size threshold for pulling files from remote in bytes.
     *
     * @var int
     */
    private $sizethreshold;

    /**
     * Puller constructor.
     *
     * @param object_client $client object client
     * @param object_file_system $filesystem object file system
     * @param object $config objectfs config.
     */
    public function __construct($filesystem, $config, $logger) {
        parent::__construct($filesystem, $config);
        $this->sizethreshold = $config->sizethreshold;

        $this->logger = $logger;
        // Inject our logger into the filesystem.
        $this->filesystem->set_logger($this->logger);
    }

    protected function get_query_name() {
        return 'get_pull_candidates';
    }

    protected function get_candidates_sql() {
        $sql = 'SELECT MAX(f.id),
                       f.contenthash,
                       MAX(f.filesize) AS filesize
                  FROM {files} f
             LEFT JOIN {tool_objectfs_objects} o ON f.contenthash = o.contenthash
              GROUP BY f.contenthash,
                       f.filesize,
                       o.location
                HAVING MAX(f.filesize) <= ?
                       AND (o.location = ?)';

        return $sql;
    }

    protected function get_candidates_sql_params() {
        $params = array($this->sizethreshold, OBJECT_LOCATION_EXTERNAL);

        return $params;
    }

    protected function manipulate_object($objectrecord) {
        $newlocation = $this->filesystem->copy_object_from_external_to_local_by_hash($objectrecord->contenthash, $objectrecord->filesize);
        return $newlocation;
    }
}


