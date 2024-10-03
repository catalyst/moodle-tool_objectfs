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

use core\check\result;
use core\task\manager;
use tool_objectfs\check\tagging_migration_status;
use tool_objectfs\task\update_object_tags;
use tool_objectfs\tests\tool_objectfs_testcase;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../tool_objectfs_testcase.php');

/**
 * Tagging migration status check tests
 *
 * @package   tool_objectfs
 * @author    Matthew Hilton <matthewhilton@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \tool_objectfs\check\tagging_migration_status
 */
class tagging_migration_status_test extends tool_objectfs_testcase {
    /**
     * Tests scenario that returns N/A
     */
    public function test_get_result_na() {
        // Regardless if this is disabled, the check should still return a non n/a status.
        $this->enable_filesystem_and_set_tagging(false);
        $check = new tagging_migration_status();
        $this->assertEquals(result::NA, $check->get_result()->get_status());
    }

    /*
     * Test scenario that returns WARNING
     */
    public function test_get_result_warning() {
        // Regardless if this is disabled, the check should still return a non n/a status.
        $this->enable_filesystem_and_set_tagging(false);

        $task = new update_object_tags();
        $task->set_fail_delay(64);
        manager::queue_adhoc_task($task);

        $check = new tagging_migration_status();
        $this->assertEquals(result::WARNING, $check->get_result()->get_status());
    }

    /*
     * Test scenario that returns OK
     */
    public function test_get_result_ok() {
        // Regardless if this is disabled, the check should still return a non n/a status.
        $this->enable_filesystem_and_set_tagging(false);

        $task = new update_object_tags();
        manager::queue_adhoc_task($task);

        $check = new tagging_migration_status();
        $this->assertEquals(result::OK, $check->get_result()->get_status());
    }
}
