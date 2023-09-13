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
 * Task that deletes empty dirs from $CFG->filedir.
 *
 * @package   tool_objectfs
 * @author    Gleimer Mora <gleimermora@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\task;

use coding_exception;
use tool_objectfs\local\manager;
use core\cron;

defined('MOODLE_INTERNAL') || die();

class delete_local_empty_directories  extends task {

    /** @var string $stringname  */
    protected $stringname = 'delete_local_empty_directories_task';

    /**
     * Execute task
     * @throws coding_exception
     */
    public function execute() {
        if (!$this->enabled_tasks()) {
            return;
        }
        // If config is set to not deletelocal objects, don't run directory clean up either.
        $config = manager::get_objectfs_config();
        if (!$config->deletelocal) {
            return;
        }
        $filesystem = new $this->config->filesystem();
        cron::trace_time_and_memory();
        $filesystem->delete_empty_dirs();
    }
}
