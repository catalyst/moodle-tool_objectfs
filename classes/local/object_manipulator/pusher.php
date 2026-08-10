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
 * Pushes files to remote storage if they meet the configured criterea.
 *
 * @package   tool_objectfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\local\object_manipulator;

use stdClass;
use tool_objectfs\local\location_helper;
use tool_objectfs\local\store\object_file_system;
use tool_objectfs\log\aggregate_logger;

/**
 * pusher
 */
class pusher extends manipulator {
    /**
     * Minimum age of a file to be pushed to remote in seconds.
     *
     * @var int
     */
    private $minimumage;

    /**
     * The maximum upload file size in bytes.
     *
     * @var int
     */
    private $maximumfilesize;

    /**
     * Get query name for logging.
     * @return string
     */
    public static function get_query_name(): string {
        return 'get_push_candidates';
    }

    /**
     * Get candidate objects to push to remote storage.
     * @param stdClass $config Plugin config.
     * @return array
     */
    public static function get_candidates(stdClass $config): array {
        global $DB;
        $filesystem = new $config->filesystem();
        $locationconds = location_helper::bits_to_sql_conditions(
            OBJECT_LOCATION_IN_FILEDIR | OBJECT_LOCATION_IN_MDL_FILES,
            OBJECT_LOCATION_IN_REMOTE
        );
        $sql = "SELECT contenthash, filesize
                  FROM {tool_objectfs_objects}
                 WHERE {$locationconds}
                   AND filesize > :threshold
                   AND filesize < :max_filesize
                   AND timeduplicated <= :maxage";
        $params = [
            'threshold' => $config->sizethreshold,
            'max_filesize' => $filesystem->get_maximum_upload_filesize(),
            'maxage' => time() - $config->minimumage,
        ];
        return $DB->get_records_sql($sql, $params, 0, $config->batchsize);
    }

    /**
     * pusher constructor.
     * @param object_file_system $filesystem
     * @param stdClass $config
     * @param aggregate_logger $logger
     */
    public function __construct(object_file_system $filesystem, stdClass $config, aggregate_logger $logger) {
        parent::__construct($filesystem, $config, $logger);
        $this->sizethreshold = $config->sizethreshold;
        $this->minimumage = $config->minimumage;
        $this->maximumfilesize = $this->filesystem->get_maximum_upload_filesize();
    }

    /**
     * manipulate_object
     * @param stdClass $objectrecord
     * @return int
     */
    public function manipulate_object(stdClass $objectrecord) {
        $contenthash = $objectrecord->contenthash;
        $filesize = $objectrecord->filesize;
        return $this->filesystem->copy_object_from_local_to_external_by_hash($contenthash, $filesize);
    }
}
