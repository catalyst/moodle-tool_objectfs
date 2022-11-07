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

use tool_objectfs\tests\tool_objectfs_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("$CFG->dirroot/admin/tool/objectfs/tests/tool_objectfs_testcase.php");

/**
 * Test adhoc-task populate_objects_filesize.
 *
 * @package    tool_objectfs
 * @author     Andrew Madden <andrewmadden@catalyst-au.net>
 * @copyright  2022 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \tool_objectfs\task\populate_objects_filesize
 */
class populate_objects_filesize_test extends tool_objectfs_testcase {

    /**
     * This method runs before every test.
     */
    public function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Test multiple objects have their filesize updated.
     */
    public function test_empty_filesizes_updated() {
        global $DB;
        $this->create_local_file("Test 1");
        $this->create_local_file("Test 2");
        $this->create_local_file("Test 3");
        $this->create_local_file("Test 4");
        $this->create_local_file("This is a looong name");

        // Set all objects to have a filesize of null.
        $DB->set_field('tool_objectfs_objects', 'filesize', null);

        // Call ad-hoc task to populate filesizes.
        $task = new \tool_objectfs\task\populate_objects_filesize();
        $task->execute();

        // Get all objects.
        $objects = $DB->get_records('tool_objectfs_objects');

        // Test all object records have populated file sizes.
        $this->assertCount(5, $objects);
        foreach ($objects as $object) {
            $this->assertNotEmpty($object->filesize);
        }
    }

    /**
     * Test filesize is not updated from 0, if file is empty.
     */
    public function test_empty_filesizes_with_real_empty_files_not_updated() {
        global $DB;
        $this->create_local_file("");

        // Set all objects to have a filesize of null.
        $DB->set_field('tool_objectfs_objects', 'filesize', null);

        // Call ad-hoc task to populate filesizes.
        $task = new \tool_objectfs\task\populate_objects_filesize();
        $task->execute();

        // Get all objects.
        $objects = $DB->get_records('tool_objectfs_objects');

        // Test object record has empty file size.
        $this->assertCount(1, $objects);
        foreach ($objects as $object) {
            $this->assertEmpty($object->filesize);
        }
    }

    /**
     * Test that file size is updated accurately.
     */
    public function test_filesize_updated_accurately() {
        global $DB;
        $file1 = $this->create_local_file("four");
        $file2 = $this->create_local_file("five5");

        // Set all objects to have a filesize of null.
        $DB->set_field('tool_objectfs_objects', 'filesize', null);

        // Call ad-hoc task to populate filesizes.
        $task = new \tool_objectfs\task\populate_objects_filesize();
        $task->execute();

        // Get all objects.
        $objects = $DB->get_records('tool_objectfs_objects');

        // Test all object records have populated file sizes.
        $this->assertCount(2, $objects);
        foreach ($objects as $object) {
            if ($object->contenthash == $file1->get_contenthash()) {
                $this->assertEquals(4, $object->filesize);
            } else if ($object->contenthash == $file2->get_contenthash()) {
                $this->assertEquals(5, $object->filesize);
            }
        }
    }

    /**
     * Test that the maxupdates value limits the number of records updated and new adhoc task is scheduled.
     */
    public function test_only_max_update_records_are_updated() {
        global $DB;
        $this->create_local_file("Test 1");
        $this->create_local_file("Test 2");
        $this->create_local_file("Test 3");
        $this->create_local_file("Test 4");
        $this->create_local_file("This is a looong name");

        // Set all objects to have a filesize of null.
        $DB->set_field('tool_objectfs_objects', 'filesize', null);

        // Call ad-hoc task to populate filesizes.
        $task = new \tool_objectfs\task\populate_objects_filesize();
        $task->set_custom_data(['maxupdates' => 2]);
        $task->execute();

        // Get all objects.
        $objects = $DB->get_records('tool_objectfs_objects');
        $updatedobjects = array_filter($objects, function($object) {
            return isset($object->filesize);
        });

        // Test only 2 records have updated filesize.
        $this->assertCount(5, $objects);
        $this->assertCount(2, $updatedobjects);

        // Test new adhoc task was scheduled.
        if (!method_exists(\core\task\manager::class, 'get_adhoc_tasks')) {
            $this->markTestSkipped('\core\task\manager::get_adhoc_tasks() not available before Moodle 3.4');
        }
        $adhoctasks = \core\task\manager::get_adhoc_tasks(populate_objects_filesize::class);
        $this->assertCount(1, $adhoctasks);
    }

    /**
     * Test that filesizes that are already populated are not pulled again.
     */
    public function test_that_non_null_values_are_not_updated() {
        global $DB;
        $this->create_local_file("Test 1");
        $this->create_local_file("Test 2");
        $this->create_local_file("Test 3");
        $this->create_local_file("Test 4");
        $this->create_local_file("This is a looong name");

        // Set all objects to have a filesize of null.
        $DB->set_field('tool_objectfs_objects', 'filesize', null);

        // Call ad-hoc task to populate filesizes.
        $task = new \tool_objectfs\task\populate_objects_filesize();
        $task->set_custom_data(['maxupdates' => 2]);
        $task->execute();

        // Get all objects.
        $objects = $DB->get_records('tool_objectfs_objects');
        $updatedobjects = array_filter($objects, function($object) {
            return isset($object->filesize);
        });

        // Test only 2 records have updated filesize.
        $this->assertCount(5, $objects);
        $this->assertCount(2, $updatedobjects);

        // Call ad-hoc task to populate filesizes.
        $task = new \tool_objectfs\task\populate_objects_filesize();
        $task->set_custom_data(['maxupdates' => 2]);
        $task->execute();

        // Get all objects.
        $objects = $DB->get_records('tool_objectfs_objects');
        $updatedobjects = array_filter($objects, function($object) {
            return isset($object->filesize);
        });

        // Test that 4 records have now been updated.
        $this->assertCount(5, $objects);
        $this->assertCount(4, $updatedobjects);
    }

    /**
     * Test objects that are marked as orphans and hence have no associated file record are not updated.
     */
    public function test_orphaned_objects_are_not_updated() {
        global $DB;
        $file1 = $this->create_local_file("Test 1");
        $this->create_local_file("Test 2");
        $this->create_local_file("Test 3");
        $this->create_local_file("Test 4");
        $this->create_local_file("This is a looong name");

        // Set all objects to have a filesize of null.
        $DB->set_field('tool_objectfs_objects', 'filesize', null);

        // Set first object to be orphaned.
        $DB->set_field('tool_objectfs_objects', 'location', -2, ['contenthash' => $file1->get_contenthash()]);

        // Call ad-hoc task to populate filesizes.
        $task = new \tool_objectfs\task\populate_objects_filesize();
        $task->execute();

        // Get all objects.
        $objects = $DB->get_records('tool_objectfs_objects');
        $updatedobjects = array_filter($objects, function($object) {
            return isset($object->filesize);
        });

        // Test that 4 records have now been updated.
        $this->assertCount(5, $objects);
        $this->assertCount(4, $updatedobjects);
    }

    /**
     * Tests objects with an error for location are not updated to prevent unexpected behaviour.
     */
    public function test_objects_with_error_are_not_updated() {
        global $DB;
        $file1 = $this->create_local_file("Test 1");
        $this->create_local_file("Test 2");
        $this->create_local_file("Test 3");
        $this->create_local_file("Test 4");
        $this->create_local_file("This is a looong name");

        // Set all objects to have a filesize of null.
        $DB->set_field('tool_objectfs_objects', 'filesize', null);

        // Set first object to be orphaned.
        $DB->set_field('tool_objectfs_objects', 'location', -1, ['contenthash' => $file1->get_contenthash()]);

        // Call ad-hoc task to populate filesizes.
        $task = new \tool_objectfs\task\populate_objects_filesize();
        $task->execute();

        // Get all objects.
        $objects = $DB->get_records('tool_objectfs_objects');
        $updatedobjects = array_filter($objects, function($object) {
            return isset($object->filesize);
        });

        // Test that 4 records have now been updated.
        $this->assertCount(5, $objects);
        $this->assertCount(4, $updatedobjects);
    }
}
