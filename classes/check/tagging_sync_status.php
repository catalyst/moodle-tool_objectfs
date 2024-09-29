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
use html_table;
use html_writer;
use tool_objectfs\local\tag\tag_manager;

/**
 * Tagging sync status check
 *
 * @package   tool_objectfs
 * @author    Matthew Hilton <matthewhilton@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tagging_sync_status extends check {
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
        if (!tag_manager::is_tagging_enabled_and_supported()) {
            return new result(result::NA, get_string('check:tagging:na', 'tool_objectfs'));
        }

        $statuses = tag_manager::get_tag_sync_status_summary();
        $table = new html_table();
        $table->head = [
            get_string('table:status', 'tool_objectfs'),
            get_string('table:objectcount', 'tool_objectfs'),
        ];

        foreach (tag_manager::SYNC_STATUSES as $status) {
            // If no objects have a status, they won't appear in the SQL above.
            // In this case, just show zero (so the use knows it exists, but is zero).
            $count = isset($statuses[$status]->statuscount) ? $statuses[$status]->statuscount : 0;
            $table->data[$status] = [tag_manager::get_sync_status_string($status), $count];
        }
        $table = html_writer::table($table);

        if (!empty($statuses[tag_manager::SYNC_STATUS_ERROR])) {
            return new result(result::WARNING, get_string('check:tagging:syncerror', 'tool_objectfs'), $table);
        }

        return new result(result::OK, get_string('check:tagging:syncok', 'tool_objectfs'), $table);
    }
}
