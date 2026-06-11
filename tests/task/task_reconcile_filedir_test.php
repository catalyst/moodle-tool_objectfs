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

use tool_objectfs\tests\testcase;
use tool_objectfs\task\reconcile_filedir;

/**
 * Test the reconcile_filedir scheduled task.
 *
 * @covers \tool_objectfs\task\reconcile_filedir
 * @package   tool_objectfs
 * @author    Alex Damsted <alexdamsted@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class task_reconcile_filedir_test extends testcase {
    public function test_reconcile_filedir_behaviours(): void {
        global $DB, $CFG;

        $this->resetAfterTest(true);

        // Enable ObjectFS test filesystem.
        $this->enable_filesystem_and_set_tagging(false);

        // Ensure deletions work immediately for tests.
        set_config('deletelocal', 1, 'tool_objectfs');
        // Minorphanage needs to be young enough to find deletions.
        set_config('minorphanedage', 0, 'tool_objectfs');
        // Set an empty last hash.
        set_config('reconcile_file_lasthash', '', 'tool_objectfs');

        // 1. Orphan file (exists on disk, not in mdl_files).
        $orphanfile = $this->create_local_file('orphan content');
        $this->delete_draft_files($orphanfile->get_contenthash());

        // 2. File exists in mdl_files but not in ObjectFS, should insert.
        $insertfile = $this->create_local_file('insert content');
        $DB->delete_records('tool_objectfs_objects', ['contenthash' => $insertfile->get_contenthash()]);

        // 3. File already duplicated in ObjectFS, should remain unchanged.
        $existingfile = $this->create_duplicated_file('existing content');

        // 4. File marked EXTERNAL but exists locally, should update to DUPLICATED.
        $remotefile = $this->create_local_file('remote content');
        $DB->set_field(
            'tool_objectfs_objects',
            'location',
            OBJECT_LOCATION_EXTERNAL,
            ['contenthash' => $remotefile->get_contenthash()]
        );

        // Execute scheduled task.
        ob_start();
        $task = new reconcile_filedir();
        $task->execute();
        ob_end_clean();

        // Assertions.

        // 1. Orphan file should be deleted from disk.
        $this->assertFalse(
            $this->is_locally_readable_by_hash($orphanfile->get_contenthash()),
            'Orphan file should have been deleted.'
        );

        // 2. Inserted file should now exist in ObjectFS.
        $this->assertTrue(
            $DB->record_exists('tool_objectfs_objects', ['contenthash' => $insertfile->get_contenthash()]),
            'Inserted file should exist in ObjectFS.'
        );
        $this->assertTrue(
            $this->is_locally_readable_by_hash($insertfile->get_contenthash()),
            'Inserted file should still be locally readable.'
        );

        // 3. Existing duplicated file should still exist.
        $this->assertTrue(
            $DB->record_exists('tool_objectfs_objects', ['contenthash' => $existingfile->get_contenthash()]),
            'Existing duplicated file should still exist in ObjectFS.'
        );
        $this->assertTrue(
            $this->is_locally_readable_by_hash($existingfile->get_contenthash()),
            'Existing duplicated file should still be locally readable.'
        );

        // 4. External file should now be marked as DUPLICATED.
        $updatedobject = $DB->get_record(
            'tool_objectfs_objects',
            ['contenthash' => $remotefile->get_contenthash()],
            '*',
            MUST_EXIST
        );
        $this->assertEquals(
            OBJECT_LOCATION_DUPLICATED,
            $updatedobject->location,
            'External file should now be marked as DUPLICATED.'
        );

        // 5. Resume hash should be cleared after full run.
        $this->assertSame(
            '',
            get_config('tool_objectfs', 'reconcile_file_lasthash'),
            'Last hash should be cleared after full reconciliation.'
        );
    }
}
