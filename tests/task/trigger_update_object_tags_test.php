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

use advanced_testcase;
use core\task\manager;

/**
 * Tests trigger_update_object_tags
 *
 * @package   tool_objectfs
 * @author    Matthew Hilton <matthewhilton@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class trigger_update_object_tags_test extends advanced_testcase {
    /**
     * Tests executing scheduled task.
     * @covers \tool_objectfs\task\trigger_update_object_tags::execute
     */
    public function test_execute() {
        $this->resetAfterTest();

        $task = new trigger_update_object_tags();
        $task->execute();

        // Ensure it spawned an adhoc task.
        $queuedadhoctasks = manager::get_adhoc_tasks(update_object_tags::class);
        $this->assertCount(1, $queuedadhoctasks);

        // Ensure the adhoc task spawned has an iteration of 1.
        $adhoctask = current($queuedadhoctasks);
        $this->assertNotEmpty($adhoctask->get_custom_data());
        $this->assertEquals(1, $adhoctask->get_custom_data()->iteration);
    }
}
