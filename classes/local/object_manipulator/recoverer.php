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
 * Recovers objects that are in the error state if it can.
 *
 * @package   tool_objectfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\local\object_manipulator;

defined('MOODLE_INTERNAL') || die();

class recoverer extends manipulator {

    /**
     * recoverer constructor.
     *
     * @param sss_client $client S3 client
     * @param object_file_system $filesystem S3 file system
     * @param object $config sssfs config.
     */
    public function __construct($filesystem, $config, $logger) {
        parent::__construct($filesystem, $config);

        $this->logger = $logger;
        // Inject our logger into the filesystem.
        $this->filesystem->set_logger($this->logger);
    }

    protected function get_query_name() {
        return 'get_recover_candidates';
    }

    /**
     * @return string
     */
    protected function get_candidates_sql() {
        return 'SELECT MAX(f.id) AS fid,
                       o.id,
                       f.contenthash,
                       MAX(f.filesize) AS filesize
                  FROM {files} f
                  JOIN {tool_objectfs_objects} o ON f.contenthash = o.contenthash
                 WHERE o.location = ?
              GROUP BY o.id,
                       f.contenthash,
                       f.filesize,
                       o.location';
    }

    protected function get_candidates_sql_params() {
        $params = array(OBJECT_LOCATION_ERROR);

        return $params;
    }

    protected function manipulate_object($objectrecord) {
        $newlocation = $this->filesystem->get_object_location_from_hash($objectrecord->contenthash, $objectrecord->filesize);
        return $newlocation;
    }

}
