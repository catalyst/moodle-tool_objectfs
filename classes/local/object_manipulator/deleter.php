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
 * Deletes files that are old enough and are in S3.
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

class deleter extends manipulator {

    /**
     * How long file must exist after
     * duplication before it can be deleted.
     *
     * @var int
     */
    private $consistencydelay;

    /**
     * Whether to delete local files
     * once they are in remote.
     *
     * @var bool
     */
    private $deletelocal;

    /**
     * deleter constructor.
     * @param object_file_system $filesystem
     * @param config $config
     * @param aggregate_logger $logger
     */
    public function __construct(object_file_system $filesystem, config $config, aggregate_logger $logger) {
        parent::__construct($filesystem, $config, $logger);
        $this->consistencydelay = $config->get('consistencydelay');
        $this->deletelocal = $config->get('deletelocal');
        $this->sizethreshold = $config->get('sizethreshold');
    }

    /**
     * @param stdClass $objectrecord
     * @return int
     */
    public function manipulate_object(stdClass $objectrecord) {
        $newlocation = $this->filesystem->delete_object_from_local_by_hash($objectrecord->contenthash, $objectrecord->filesize);
        return $newlocation;
    }

    /**
     * @return bool
     */
    protected function manipulator_can_execute() {
        if ($this->deletelocal == 0) {
            mtrace("Delete local disabled \n");
            return false;
        }

        return true;
    }
}
