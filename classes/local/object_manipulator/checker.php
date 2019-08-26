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
 * Checks files to have their location stored in database.
 *
 * @package   tool_objectfs
 * @author    Mikhail Golenkov <mikhailgolenkov@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\local\object_manipulator;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/admin/tool/objectfs/lib.php');

class checker extends manipulator {

    /**
     * Pusher constructor.
     *
     * @param object_client $client remote object client
     * @param object_file_system $filesystem objectfs file system
     * @param object $config objectfs config.
     */
    public function __construct($filesystem, $config, $logger) {
        parent::__construct($filesystem, $config);

        $this->logger = $logger;
        // Inject our logger into the filesystem.
        $this->filesystem->set_logger($this->logger);
    }

    protected function get_query_name() {
        return 'get_check_candidates';
    }

    protected function get_candidates_sql() {
        $sql = 'SELECT f.contenthash
                  FROM mdl_files f LEFT JOIN mdl_tool_objectfs_objects o ON f.contenthash = o.contenthash
                 WHERE f.filesize > 0
                       AND o.location is NULL
              GROUP BY f.contenthash';

        return $sql;
    }

    protected function get_candidates_sql_params() {
        $params = array();

        return $params;
    }

    protected function manipulate_object($objectrecord) {
        $location = $this->filesystem->get_object_location_from_hash($objectrecord->contenthash);
        return $location;
    }
}
