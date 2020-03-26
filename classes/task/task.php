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
 * Base abstract class for objectfs tasks.
 *
 * @package   tool_objectfs
 * @author    Gleimer Mora <gleimermora@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\task;

use coding_exception;
use moodle_exception;
use stdClass;
use tool_objectfs\local\manager;
use tool_objectfs\local\object_manipulator\manipulator_builder;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/admin/tool/objectfs/lib.php');

abstract class task extends \core\task\scheduled_task implements objectfs_task {

    /** @var stdClass $config */
    protected $config;

    /**
     * task constructor.
     */
    public function __construct() {
        $this->config = manager::get_objectfs_config();
    }

    /**
     * Get task name
     * @return string
     * @throws coding_exception
     */
    public function get_name() {
        return get_string($this->stringname, 'tool_objectfs');
    }

    /**
     * Execute task
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function execute() {
        if ($this->enabled_tasks()) {
            (new manipulator_builder())->execute($this->manipulator);
        }
    }

    /**
     * @return bool
     * @throws coding_exception
     */
    protected function enabled_tasks() {
        $enabletasks = (bool)$this->config->enabletasks;
        if (!$enabletasks) {
            mtrace(get_string('not_enabled', 'tool_objectfs'));
        }
        return $enabletasks;
    }
}
