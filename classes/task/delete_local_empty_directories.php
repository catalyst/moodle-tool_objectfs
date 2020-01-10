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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/admin/tool/objectfs/lib.php');
require_once($CFG->libdir.'/cronlib.php');

class delete_local_empty_directories  extends \core\task\scheduled_task {

    /**
     * Get task name
     * @return string
     * @throws \coding_exception
     */
    public function get_name() {
        return get_string('delete_local_empty_directories_task', 'tool_objectfs');
    }

    /**
     * Execute task
     * @throws \coding_exception
     */
    public function execute() {
        $config = get_objectfs_config();

        if (!tool_objectfs_should_tasks_run()) {
            mtrace(get_string('not_enabled', 'tool_objectfs'));
            return;
        }
        $filesystem = new $config->filesystem();
        cron_trace_time_and_memory();
        $deletedfiles = $filesystem->delete_empty_folders();
        mtrace('... ' . get_string('total_deleted_dirs', 'tool_objectfs') . $deletedfiles);
    }
}
