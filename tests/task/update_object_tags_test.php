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
use moodle_exception;
use tool_objectfs\local\tag\tag_manager;
use tool_objectfs\tests\testcase;

/**
 * Tests update_object_tags
 *
 * @package   tool_objectfs
 * @author    Matthew Hilton <matthewhilton@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \tool_objectfs\task\update_object_tags
 */
class update_object_tags_test extends testcase {
    /**
     * Enables tagging in config and sets up the filesystem to allow tagging
     */
    private function set_tagging_enabled() {
        global $CFG;
        $config = \tool_objectfs\local\manager::get_objectfs_config();
        $config->taggingenabled = true;
        $config->enabletasks = true;
        $config->filesystem = '\\tool_objectfs\\tests\\test_file_system';
        \tool_objectfs\local\manager::set_objectfs_config($config);
        $CFG->phpunit_objectfs_supports_object_tagging = true;
    }

    /**
     * Creates object with tags needing to be synced
     * @param string $contents contents of object to create.
     * @return stdClass object record
     */
    private function create_object_needing_tag_sync(string $contents) {
        global $DB;
        $object = $this->create_duplicated_object($contents);
        $DB->set_field('tool_objectfs_objects', 'tagsyncstatus', tag_manager::SYNC_STATUS_NEEDS_SYNC, ['id' => $object->id]);
        return $object;
    }

    /**
     * Tests task exits when the tagging feature is disabled.
     */
    public function test_not_enabled() {
        $this->resetAfterTest();

        // By default filesystem does not support and tagging not enabled, so should error.
        $task = new update_object_tags();

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('tagging:migration:notsupported', 'tool_objectfs'));
        $task->execute();
    }

    /**
     * Tests handles an invalid iteration limit
     */
    public function test_invalid_iteration_limit() {
        $this->resetAfterTest();
        $this->set_tagging_enabled();

        // This should be greater than 1, if zero should error.
        set_config('maxtaggingiterations', 0, 'tool_objectfs');

        // Give it a valid iteration number though.
        $task = new update_object_tags();
        $task->set_custom_data(['iteration' => 5]);

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('tagging:migration:invaliditerations', 'tool_objectfs'));
        $task->execute();
    }

    /**
     * Tests handles an invalid number of iterations in custom data
     */
    public function test_invalid_iteration_number() {
        $this->resetAfterTest();
        $this->set_tagging_enabled();

        // Give it a valid max iteration number.
        set_config('maxtaggingiterations', 5, 'tool_objectfs');

        // But don't set the iteration number on the customdata at all.
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('tagging:migration:invaliditerations', 'tool_objectfs'));

        $task = new update_object_tags();
        $task->execute();
    }

    /**
     * Tests exits when there are no more objects needing to be synced
     */
    public function test_no_more_objects_to_sync() {
        $this->resetAfterTest();
        $this->set_tagging_enabled();
        set_config('maxtaggingiterations', 5, 'tool_objectfs');
        $task = new update_object_tags();
        $task->set_custom_data(['iteration' => 1]);

        // This should not error, only output a string since it is successfully completed.
        $this->expectOutputString("No more objects found that need tagging, exiting.\n");
        $task->execute();
    }

    /**
     * Tests maxtaggingiterations is correctly checked
     */
    public function test_max_iterations() {
        $this->resetAfterTest();
        $this->set_tagging_enabled();

        // Set max 1 iteration.
        set_config('maxtaggingiterations', 1, 'tool_objectfs');
        set_config('maxtaggingperrun', 100, 'tool_objectfs');

        $task = new update_object_tags();

        // Give it an iteration number higher.
        $task->set_custom_data(['iteration' => 5]);

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('tagging:migration:limitreached', 'tool_objectfs', 5));
        $task->execute();
    }

    /**
     * Tests a successful tagging run where it needs to requeue for further processing
     */
    public function test_tagging_run_with_requeue() {
        $this->resetAfterTest();
        $this->set_tagging_enabled();

        // Set max 1 object per run.
        set_config('maxtaggingperrun', 1, 'tool_objectfs');
        set_config('maxtaggingiterations', 5, 'tool_objectfs');

        // Create two objects needing sync.
        $this->create_object_needing_tag_sync('object 1');
        $this->create_object_needing_tag_sync('object 2');
        $this->assertCount(2, tag_manager::get_objects_needing_sync(100));

        $task = new update_object_tags();
        $task->set_custom_data(['iteration' => 1]);

        $this->expectOutputString("Requeing self for another iteration.\n");
        $task->execute();

        // Ensure that 1 object had its sync status updated.
        $this->assertCount(1, tag_manager::get_objects_needing_sync(100));

        // Ensure there is another task that was re-queued with the iteration incremented.
        $tasks = manager::get_adhoc_tasks(update_object_tags::class);
        $this->assertCount(1, $tasks);
        $task = current($tasks);
        $this->assertNotEmpty($task->get_custom_data());
        $this->assertEquals(2, $task->get_custom_data()->iteration);
    }

    /**
     * Tests get_iteration
     * @covers \tool_objectfs\task\update_object_tags::get_iteration
     */
    public function test_get_iteration() {
        $task = new update_object_tags();

        // No custom data, should return zero.
        $this->assertEquals(0, $task->get_iteration());

        // Set iteration, it should return that.
        $task->set_custom_data([
            'iteration' => 5,
        ]);
        $this->assertEquals(5, $task->get_iteration());
    }

    /**
     * Tests getting status badge and summary html
     * @covers \tool_objectfs\task\update_object_tags::get_status_badge
     * @covers \tool_objectfs\task\update_object_tags::get_summary_html
     */
    public function test_get_summary_html_and_status_badge() {
        // Spawn three tasks and break each one in a different way.
        // Test their badge output.
        $task1 = new update_object_tags();
        $this->assertStringContainsString(get_string('status:waiting', 'tool_objectfs'),
            $task1->get_status_badge());

        $task2 = new update_object_tags();
        $task2->set_fail_delay(1000);
        $this->assertStringContainsString(get_string('status:failing', 'tool_objectfs', 1000),
            $task2->get_status_badge());

        $task3 = new update_object_tags();
        $task3->set_timestarted(1000);
        $this->assertStringContainsString(get_string('status:running', 'tool_objectfs'),
            $task3->get_status_badge());

        // Now queue them so we can test report generation.
        manager::queue_adhoc_task($task1);
        manager::queue_adhoc_task($task2);
        manager::queue_adhoc_task($task3);

        // Ensure row count is expected (excluding header row).
        $report = update_object_tags::get_summary_html();
        $rowcount = substr_count($report, '<tr') - 1;
        $this->assertEquals(3, $rowcount);
    }
}
