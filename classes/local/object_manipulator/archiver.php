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
 * Archives {tool_objectfs_objects} records for files that have been
 * from the core {files} table.
 *
 * @package   tool_objectfs
 * @author    Nathan Mares <ngmares@gmail.com>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\local\object_manipulator;

use stdClass;
use tool_objectfs\local\store\object_file_system;
use tool_objectfs\log\aggregate_logger;
use tool_objectfs\local\manager;

defined('MOODLE_INTERNAL') || die();

class archiver extends manipulator {

    /**
     * deleter constructor.
     * @param object_file_system $filesystem
     * @param stdClass $config
     * @param aggregate_logger $logger
     */
    public function __construct(object_file_system $filesystem, stdClass $config, aggregate_logger $logger) {
        parent::__construct($filesystem, $config, $logger);
    }

    /**
     * @param stdClass $objectrecord
     * @return int
     */
    public function manipulate_object(stdClass $objectrecord) {
        manager::update_object_by_hash($objectrecord->contenthash, OBJECT_LOCATION_ARCHIVED);
        return true;
    }

    /**
     * @return bool
     */
    protected function manipulator_can_execute() {
        return true;
    }
}
