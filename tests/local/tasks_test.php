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

namespace tool_objectfs\local;

use tool_objectfs\tests\test_file_system;

/**
 * End to end tests for tasks. Make sure all the plumbing is ok.
 *
 * @covers \tool_objectfs\local\manager
 */
class tasks_test extends \tool_objectfs\tests\testcase {

    protected function setUp(): void {
        parent::setUp();
        ob_start();
    }

    protected function tearDown(): void {
        ob_end_clean();
    }

    public static function get_orphaned_delayed_count() {
        global $DB;

        $orphanedcount = $DB->count_records_sql("SELECT COUNT(id) FROM {tool_objectfs_objects} WHERE timeorphaned > 0");

        return $orphanedcount;
    }

    public function test_run_legacy_cron() {
        $config = manager::get_objectfs_config();
        $config->enabletasks = true;
        manager::set_objectfs_config($config);
        $this->assertTrue(tool_objectfs_cron());
    }

    public function test_run_scheduled_tasks() {
        global $CFG;
        // If tasks not implemented.
        if ($CFG->branch <= 26) {
            return true;
        }

        $config = manager::get_objectfs_config();
        $config->enabletasks = true;
        $config->filesystem = '\\tool_objectfs\\tests\\test_file_system';
        manager::set_objectfs_config($config);

        $scheduledtasknames = [
            'delete_local_objects',
            'delete_local_empty_directories',
            'generate_status_report',
            'pull_objects_from_storage',
            'push_objects_to_storage',
            'recover_error_objects',
            'check_objects_location',
            'delete_orphaned_object_metadata',
        ];

        foreach ($scheduledtasknames as $taskname) {
            $task = \core\task\manager::get_scheduled_task('\\tool_objectfs\\task\\' . $taskname);
            $task->execute();
        }
        $this->expectNotToPerformAssertions(); // Just check we get this far without any exceptions.
    }

    public function test_delay_delete_orphaned_object() {
        global $CFG, $DB;

        $this->resetAfterTest(true);

        $this->filesystem = new test_file_system();
        $file = $this->create_remote_file();
        $filehash = $file->get_contenthash();
        $objectrecord = $DB->get_record('tool_objectfs_objects', ['contenthash' => $filehash]);
        $file->delete(); // This makes it orphaned.

        // Set config.
        set_config('delaydeleteexternalobject', (3 * DAYSECS), 'tool_objectfs');
        set_config('maxorphanedage', (1 * DAYSECS), 'tool_objectfs');
        set_config('deleteexternal', TOOL_OBJECTFS_DELETE_EXTERNAL_TRASH, 'tool_objectfs');
        set_config('filesystem', "tool_objectfs\\tests\\test_file_system", 'tool_objectfs');

        unset($CFG->forced_plugin_settings['tool_objectfs']['deleteexternal']); // This will be reset in parent::setUp().

        // Update time so it is considered for orphaned deletion. It should be skipped due to delay setting.
        $objectrecord->timeduplicated = time() - (2 * DAYSECS); // Time for it to be considered orphaned.
        $DB->update_record('tool_objectfs_objects', $objectrecord);

        $objectrecord = manager::update_object($objectrecord, OBJECT_LOCATION_ORPHANED);

        $pretaskcount = $this->get_orphaned_delayed_count();

        $task = \core\task\manager::get_scheduled_task('\\tool_objectfs\\task\\delete_orphaned_object_metadata');
        $task->execute();

        $posttaskcount = $this->get_orphaned_delayed_count();

        $this->assertSame($pretaskcount, $posttaskcount, 'No file should have been deleted due to delay setting');

        $objectrecord->timeorphaned = time() - (4 * DAYSECS); // Time for it to be able to be deleted.
        $DB->update_record('tool_objectfs_objects', $objectrecord);

        $task = \core\task\manager::get_scheduled_task('\\tool_objectfs\\task\\delete_orphaned_object_metadata');
        $task->execute();

        $posttaskcount = $this->get_orphaned_delayed_count();

        $this->assertGreaterThan($posttaskcount, $pretaskcount, 'There should be less files after removing delayed orphaned files');
    }
}
