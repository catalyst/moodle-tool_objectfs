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
 * Task that pushes files to S3.
 *
 * @package   tool_objectfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\task;

use tool_objectfs\object_file_system;
use tool_objectfs\s3_file_system;
use tool_objectfs\object_manipulator\manipulator;


defined('MOODLE_INTERNAL') || die();

require_once( __DIR__ . '/../../lib.php');
require_once(__DIR__ . '/../../../../../config.php');
require_once($CFG->libdir . '/filestorage/file_system.php');

class delete_local_objects extends \core\task\scheduled_task {

    /**
     * Get task name
     */
    public function get_name() {
        return get_string('delete_local_objects_task', 'tool_objectfs');
    }

    /**
     * Execute task
     */
    public function execute() {
        manipulator::setup_and_run_object_manipulator('\\tool_objectfs\\object_manipulator\\deleter');
    }
}