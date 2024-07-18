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

use core\task\adhoc_task;
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
     * Execute task
     */
    public function execute() {
        if (!tag_manager::is_tagging_enabled_and_supported()) {
            mtrace("Tagging feature not enabled or supported by filesystem, exiting.");
            return;
        }

        // Since this adhoc task can requeue itself, ensure there is a fixed limit on the number
        // of times this can happen, to avoid any accidental runaways.
        $iterationlimit = get_config('tool_objectfs', 'maxtaggingiterations') ?: 0;
        $iteration = !empty($this->get_custom_data()->iteration) ? $this->get_custom_data()->iteration : 0;

        if (empty($iterationlimit) || empty($iteration)) {
            mtrace("Invalid number of iterations, exiting.");
            return;
        }

        if (abs($iteration) > abs($iterationlimit)) {
            mtrace("Maximum number of iterations reached: " . $iteration . ", exiting.");
            return;
        }

        // Get the maximum num of objects to update as configured.
        $limit = get_config('tool_objectfs', 'maxtaggingperrun');
        $contenthashes = tag_manager::get_objects_needing_sync($limit);

        if (empty($contenthashes)) {
            mtrace("No more objects found that need tagging, exiting.");
            return;
        }

        // Sanity check that fs is object file system and not anything else.
        $fs = get_file_storage()->get_file_system();

        if (!method_exists($fs, "push_object_tags")) {
            mtrace("File system is not object file system, exiting.");
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
