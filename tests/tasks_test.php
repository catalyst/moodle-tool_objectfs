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

namespace tool_objectfs\tests;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/classes/test_config.php');
require_once(__DIR__ . '/tool_objectfs_testcase.php');

/**
 * End to end tests for tasks. Make sure all the plumbing is ok.
 */
class tasks_testcase extends tool_objectfs_testcase {

    protected function setUp() {
        parent::setUp();
        ob_start();
    }

    protected function tearDown() {
        ob_end_clean();
    }

    public function test_run_legacy_cron() {
        test_config::set_config(['enabletasks' => 1]);
        tool_objectfs_cron();
    }

    public function test_run_scheduled_tasks() {
        global $CFG;
        // If tasks not implemented.
        if ($CFG->branch <= 26) {
            return true;
        }

        $config = ['enabletasks' => 1, 'filesystem' => '\\tool_objectfs\\tests\\test_file_system'];
        test_config::set_config($config);

        $scheduledtasknames = [
            'delete_local_objects',
            'delete_local_empty_directories',
            'generate_status_report',
            'pull_objects_from_storage',
            'push_objects_to_storage',
            'recover_error_objects',
            'check_objects_location',
        ];

        foreach ($scheduledtasknames as $taskname) {
            $task = \core\task\manager::get_scheduled_task('\\tool_objectfs\\task\\' . $taskname);
            $task->execute();
        }
    }

}

