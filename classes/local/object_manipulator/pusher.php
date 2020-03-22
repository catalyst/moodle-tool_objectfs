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
use tool_objectfs\config\config;
use tool_objectfs\local\store\object_file_system;
use tool_objectfs\log\aggregate_logger;

defined('MOODLE_INTERNAL') || die();

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
     * pusher constructor.
     * @param object_file_system $filesystem
     * @param config $config
     * @param aggregate_logger $logger
     */
    public function __construct(object_file_system $filesystem, config $config, aggregate_logger $logger) {
        parent::__construct($filesystem, $config, $logger);
        $this->sizethreshold = $config->get('sizethreshold');
        $this->minimumage = $config->get('minimumage');
        $this->maximumfilesize = $this->filesystem->get_maximum_upload_filesize();
    }

    /**
     * @param stdClass $objectrecord
     * @return int
     */
    public function manipulate_object(stdClass $objectrecord) {
        $contenthash = $objectrecord->contenthash;
        $filesize = $objectrecord->filesize;
        return $this->filesystem->copy_object_from_local_to_external_by_hash($contenthash, $filesize);
    }
}
