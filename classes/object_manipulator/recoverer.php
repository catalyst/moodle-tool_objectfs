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

namespace tool_objectfs\object_manipulator;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/admin/tool/objectfs/lib.php');

use Aws\S3\Exception\S3Exception;

class recoverer extends manipulator {

    /**
     * recoverer constructor.
     *
     * @param sss_client $client S3 client
     * @param object_file_system $filesystem S3 file system
     * @param object $config sssfs config.
     */
    public function __construct($filesystem, $config) {
        parent::__construct($filesystem, $config);
    }

    /**
     * Get candidate content hashes for cleaning.
     * Files that are past the consistancy delay
     * and are in location duplicated.
     *
     * @return array candidate contenthashes
     */
    public function get_candidate_objects() {
        global $DB;

        $sql = 'SELECT f.contenthash,
                       MAX(f.filesize) AS filesize
                  FROM {files} f
             LEFT JOIN {tool_objectfs_objects} o ON f.contenthash = o.contenthash
                 WHERE o.location = ?
              GROUP BY f.contenthash,
                       f.filesize,
                       o.location';

        $params = array(OBJECT_LOCATION_ERROR);

        $starttime = time();
        $objects = $DB->get_records_sql($sql, $params);
        $duration = time() - $starttime;
        $count = count($objects);

        $logstring = "Objects recoverer query took $duration seconds to find $count objects \n";
        mtrace($logstring);

        return $objects;
    }


    /**
     * CRecovers objects that are in the error state if it can.
     *
     * @param  array $candidatehashes content hashes to delete
     */
    public function execute($objects) {
        global $DB;

        $starttime = time();
        $objectcount = 0;
        $totalobjectsize = 0;

        foreach ($objects as $object) {
            if (time() >= $this->finishtime) {
                break;
            }

            $location = $this->filesystem->get_actual_object_location_by_hash($object->contenthash);

            // Then the location error has been fixed.
            if ($location !== OBJECT_LOCATION_ERROR) {
                update_object_record($object->contenthash, $location);
            }

            $objectcount++;
            $totalobjectsize += $object->filesize;
        }

        $duration = time() - $starttime;

        $totalobjectsize = display_size($totalobjectsize);
        $logstring = "Objects recoverer processed $objectcount objects, total size: $totalobjectsize in $duration seconds \n";
        mtrace($logstring);
    }
}
