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

namespace tool_objectfs\task;

use coding_exception;
use core\task\adhoc_task;
use core\task\manager;
use html_table;
use html_writer;
use moodle_exception;
use tool_objectfs\local\tag\tag_manager;

/**
 * Calculates and updates an objects tags in the external store.
 *
 * @package   tool_objectfs
 * @author    Matthew Hilton <matthewhilton@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_object_tags extends adhoc_task {
    /**
     * Returns a status badge depending on the health of the task
     * @return string
     */
    public function get_status_badge(): string {
        $identifier = '';
        $class = '';

        if ($this->get_fail_delay() > 0) {
            $identifier = 'failing';
            $class = 'badge-warning';
        } else if (!is_null($this->get_timestarted()) && $this->get_timestarted() > 0) {
            $identifier = 'running';
            $class = 'badge-info';
        } else {
            $identifier = 'waiting';
            $class = 'badge-info';
        }

        return html_writer::span(get_string('status:'.$identifier, 'tool_objectfs', $this->get_fail_delay()), 'badge ' . $class);
    }

    /**
     * Returns iteration count
     * @return int
     */
    public function get_iteration(): int {
        return !empty($this->get_custom_data()->iteration) ? $this->get_custom_data()->iteration : 0;
    }

    /**
     * Execute task
     */
    public function execute() {
        if (!tag_manager::is_tagging_enabled_and_supported()) {
            // Site admin should know if this migration is running but the fs doesn't support tagging
            // (maybe they changed fs mid-run?).
            throw new moodle_exception('tagging:migration:notsupported', 'tool_objectfs');
        }

        // Since this adhoc task can requeue itself, ensure there is a fixed limit on the number
        // of times this can happen, to avoid any accidental runaways.
        $iterationlimit = get_config('tool_objectfs', 'maxtaggingiterations') ?: 0;
        $iteration = $this->get_iteration();

        if (empty($iterationlimit) || empty($iteration) || $iterationlimit < 0 || $iteration < 0) {
            // This should never hit here, if it does something is very wrong.
            // Throw exception so it causes a retry and alerts.
            throw new moodle_exception('tagging:migration:invaliditerations', 'tool_objectfs');
        }

        if ($iteration > $iterationlimit) {
            // Generally this means the site has too many objects or not enough configured iterations.
            // Regardless it should throw an exception to get the site admins attention.
            throw new moodle_exception('tagging:migration:limitreached', 'tool_objectfs', '', $iteration);
        }

        $fs = get_file_storage()->get_file_system();

        // This is checked above in tag_manager::is_tagging_enabled_and_supported, but as a sanity check
        // ensure this specific method is defined.
        if (!method_exists($fs, "push_object_tags")) {
            throw new coding_exception("Filesystem does not define push_object_tags");
        }

        // Get the maximum num of objects to update as configured.
        $limit = get_config('tool_objectfs', 'maxtaggingperrun');
        $contenthashes = tag_manager::get_objects_needing_sync($limit);

        if (empty($contenthashes)) {
            // This is ok, it means we are done. Exit silently.
            mtrace("No more objects found that need tagging, exiting.");
            return;
        }

        // For each, try to sync their tags.
        foreach ($contenthashes as $contenthash) {
            $fs->push_object_tags($contenthash);
        }

        // Re-queue self to process more in another iteration.
        mtrace("Requeing self for another iteration.");
        $task = new update_object_tags();
        $task->set_custom_data([
            'iteration' => $iteration + 1,
        ]);
        \core\task\manager::queue_adhoc_task($task);
    }
}
