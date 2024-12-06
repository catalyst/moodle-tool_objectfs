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

use core\task\manager;
use core\task\scheduled_task;

/**
 * Queues update_object_tags adhoc task periodically, or manually from the frontend.
 *
 * @package   tool_objectfs
 * @author    Matthew Hilton <matthewhilton@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class trigger_update_object_tags extends scheduled_task {
    /**
     * Task name
     */
    public function get_name() {
        return get_string('task:triggerupdateobjecttags', 'tool_objectfs');
    }
    /**
     * Execute task
     */
    public function execute() {
        // Only schedule up to the max amount, less any that are already scheduled.
        $alreadyexist = count(manager::get_adhoc_tasks(update_object_tags::class));
        $maxtoschedule = get_config('tool_objectfs', 'maxtaggingtaskstospawn');
        $toschedule = max(0, $maxtoschedule - $alreadyexist);

        for ($i = 0; $i < $toschedule; $i++) {
            // Queue adhoc task, nothing else.
            $task = new update_object_tags();
            $task->set_custom_data([
                'iteration' => 1,
            ]);
            manager::queue_adhoc_task($task);
        }
    }
}
