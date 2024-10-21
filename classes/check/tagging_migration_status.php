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

namespace tool_objectfs\check;

use core\check\check;
use core\check\result;
use core\task\manager;
use html_table;
use html_writer;
use tool_objectfs\task\update_object_tags;

/**
 * Tagging migration status check
 *
 * @package   tool_objectfs
 * @author    Matthew Hilton <matthewhilton@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tagging_migration_status extends check {
    /**
     * Link to ObjectFS settings page.
     *
     * @return \action_link|null
     */
    public function get_action_link(): ?\action_link {
        $url = new \moodle_url('/admin/category.php', ['category' => 'tool_objectfs']);
        return new \action_link($url, get_string('pluginname', 'tool_objectfs'));
    }

    /**
     * Get result
     * @return result
     */
    public function get_result(): result {
        // We want to check this regardless if enabled or supported and not exit early.
        // Because it may have been turned off accidentally thus causing the migration to fail.
        $tasks = manager::get_adhoc_tasks(update_object_tags::class);

        if (empty($tasks)) {
            return new result(result::NA, get_string('tagging:migration:nothingrunning', 'tool_objectfs'));
        }

        $table = new html_table();
        $table->head = [
            get_string('table:taskid', 'tool_objectfs'),
            get_string('table:iteration', 'tool_objectfs'),
            get_string('table:status', 'tool_objectfs'),
        ];

        foreach ($tasks as $task) {
            $table->data[$task->get_id()] = [$task->get_id(), $task->get_iteration(), $task->get_status_badge()];
        }
        $html = html_writer::table($table);

        $ataskisfailing = !empty(array_filter($tasks, function($task) {
            return $task->get_fail_delay() > 0;
        }));

        if ($ataskisfailing) {
            return new result(result::WARNING, get_string('check:tagging:migrationerror', 'tool_objectfs'), $html);
        }

        return new result(result::OK, get_string('check:tagging:migrationok', 'tool_objectfs'), $html);
    }
}
