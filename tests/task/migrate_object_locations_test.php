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

use tool_objectfs\local\location_query;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../lib.php');

/**
 * Tests for the location bits migration task.
 *
 * @covers \tool_objectfs\task\migrate_object_locations
 * @package   tool_objectfs
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class migrate_object_locations_test extends \advanced_testcase {
    /**
     * Start from a pre-migration state: empty tables, completion flag off, cursor and progress reset.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest(true);
        $DB->delete_records('tool_objectfs_objects');
        $DB->delete_records('files');
        foreach (['complete', 'lastid', 'total', 'donesofar'] as $name) {
            set_config('locationbitsmigration' . $name, 0, 'tool_objectfs');
        }
    }

    /**
     * Run the task, discarding any progress output.
     */
    private function run_task(): void {
        ob_start();
        try {
            (new migrate_object_locations())->execute();
        } finally {
            ob_end_clean();
        }
    }

    /**
     * Insert a pre-migration object row: the legacy location is set and the bit columns hold their defaults.
     *
     * @param string $name Unique name, the content hash is derived from it.
     * @param int $location OBJECT_LOCATION_* constant.
     * @param int|null $filesize Size recorded in the objects table.
     * @param int[]|null $bits Optional [in_filedir, in_mdl_files, in_remote] to simulate wrong bits.
     * @return int Row id.
     */
    private function insert_object(string $name, int $location, ?int $filesize = null, ?array $bits = null): int {
        global $DB;
        $record = [
            'contenthash' => sha1($name),
            'timeduplicated' => 0,
            'location' => $location,
            'filesize' => $filesize,
        ];
        if ($bits !== null) {
            [$record['in_filedir'], $record['in_mdl_files'], $record['in_remote']] = $bits;
        }
        return $DB->insert_record('tool_objectfs_objects', (object) $record);
    }

    /**
     * Insert a row in {files}.
     *
     * @param string $contentname Name the content hash is derived from, matching insert_object().
     * @param int $filesize Size recorded in {files}.
     * @param string|null $filename Unique file name, defaults to the content name.
     */
    private function insert_file(string $contentname, int $filesize, ?string $filename = null): void {
        global $DB;
        $filename ??= $contentname;
        $DB->insert_record('files', (object) [
            'contenthash' => sha1($contentname),
            'pathnamehash' => sha1('/' . $filename),
            'contextid' => \context_system::instance()->id,
            'component' => 'core',
            'filearea' => 'unittest',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $filename,
            'userid' => 0,
            'filesize' => $filesize,
            'mimetype' => 'text',
            'status' => 0,
            'source' => '',
            'author' => '',
            'license' => '',
            'timecreated' => time(),
            'timemodified' => time(),
            'sortorder' => 0,
            'referencefileid' => null,
        ]);
    }

    /**
     * Literal [in_filedir, in_mdl_files, in_remote] for a location, independent of location_query.
     *
     * @param int $location OBJECT_LOCATION_* constant.
     * @return int[]
     */
    private static function expected_bits(int $location): array {
        return [
            OBJECT_LOCATION_ORPHANED => [1, 0, 0],
            OBJECT_LOCATION_ERROR => [0, 1, 0],
            OBJECT_LOCATION_LOCAL => [1, 1, 0],
            OBJECT_LOCATION_DUPLICATED => [1, 1, 1],
            OBJECT_LOCATION_EXTERNAL => [0, 1, 1],
        ][$location];
    }

    /**
     * Assert an object row's bits match its legacy location.
     *
     * @param int $id Row id.
     */
    private function assert_row_consistent(int $id): void {
        global $DB;
        $row = $DB->get_record('tool_objectfs_objects', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame(
            self::expected_bits((int) $row->location),
            [(int) $row->in_filedir, (int) $row->in_mdl_files, (int) $row->in_remote],
            "Bits for object {$id} at location {$row->location}"
        );
    }

    /**
     * Get the stored filesize of an object row.
     *
     * @param int $id Row id.
     * @return int|null
     */
    private function get_filesize(int $id): ?int {
        global $DB;
        $filesize = $DB->get_field('tool_objectfs_objects', 'filesize', ['id' => $id], MUST_EXIST);
        return $filesize === null ? null : (int) $filesize;
    }

    /**
     * An empty table completes immediately without dividing by a zero total.
     */
    public function test_empty_table(): void {
        $this->run_task();

        $this->assertTrue(location_query::uses_bits());
        $this->assertSame(0, (int) get_config('tool_objectfs', 'locationbitsmigrationtotal'));
        $this->assertSame(0, (int) get_config('tool_objectfs', 'locationbitsmigrationdonesofar'));
        $this->assertSame(0, (int) get_config('tool_objectfs', 'locationbitsmigrationlastid'));
    }

    /**
     * Every legacy location gets its bit values, and progress covers all rows.
     */
    public function test_all_locations_migrated(): void {
        $ids = [];
        foreach (
            [
                OBJECT_LOCATION_ORPHANED,
                OBJECT_LOCATION_ERROR,
                OBJECT_LOCATION_LOCAL,
                OBJECT_LOCATION_DUPLICATED,
                OBJECT_LOCATION_EXTERNAL,
            ] as $location
        ) {
            $ids[$location] = $this->insert_object('location' . $location, $location, 10);
        }

        $this->assertFalse(location_query::uses_bits());
        $this->run_task();

        foreach ($ids as $id) {
            $this->assert_row_consistent($id);
        }
        $this->assertSame(5, (int) get_config('tool_objectfs', 'locationbitsmigrationtotal'));
        $this->assertSame(5, (int) get_config('tool_objectfs', 'locationbitsmigrationdonesofar'));
        // The cursor returns to zero once the pass is finished, so a later run starts fresh.
        $this->assertSame(0, (int) get_config('tool_objectfs', 'locationbitsmigrationlastid'));
        $this->assertTrue(location_query::uses_bits());
    }

    /**
     * Missing filesizes are backfilled from {files} only for objects that can have a {files} row.
     */
    public function test_filesize_backfill(): void {
        // Backfilled from the matching file.
        $local = $this->insert_object('local', OBJECT_LOCATION_LOCAL);
        $this->insert_file('local', 100);
        $error = $this->insert_object('error', OBJECT_LOCATION_ERROR);
        $this->insert_file('error', 120);

        // Several files share one content hash: the largest size is used.
        $multiple = $this->insert_object('multiple', OBJECT_LOCATION_EXTERNAL);
        $this->insert_file('multiple', 10, 'multiple-a');
        $this->insert_file('multiple', 90, 'multiple-b');

        // No matching file: stays NULL.
        $nofile = $this->insert_object('nofile', OBJECT_LOCATION_DUPLICATED);

        // Orphaned objects are not in {files}, so a coincidental file row is ignored.
        $orphaned = $this->insert_object('orphaned', OBJECT_LOCATION_ORPHANED);
        $this->insert_file('orphaned', 300);

        // An existing size is never overwritten.
        $existing = $this->insert_object('existing', OBJECT_LOCATION_LOCAL, 5);
        $this->insert_file('existing', 500);

        $this->run_task();

        $this->assertSame(100, $this->get_filesize($local));
        $this->assertSame(120, $this->get_filesize($error));
        $this->assertSame(90, $this->get_filesize($multiple));
        $this->assertNull($this->get_filesize($nofile));
        $this->assertNull($this->get_filesize($orphaned));
        $this->assertSame(5, $this->get_filesize($existing));
    }

    /**
     * A saved cursor continues the progress count instead of restarting it.
     */
    public function test_resume_from_saved_cursor(): void {
        $ids = [];
        foreach (['a', 'b', 'c', 'd', 'e'] as $i => $name) {
            // The first two rows were already migrated by an earlier run.
            $ids[] = $this->insert_object($name, OBJECT_LOCATION_LOCAL, 10, $i < 2 ? [1, 1, 0] : null);
        }

        set_config('locationbitsmigrationlastid', $ids[1], 'tool_objectfs');
        set_config('locationbitsmigrationtotal', 5, 'tool_objectfs');
        set_config('locationbitsmigrationdonesofar', 2, 'tool_objectfs');

        $this->run_task();

        // Only the three rows after the cursor were processed on top of the saved progress.
        $this->assertSame(5, (int) get_config('tool_objectfs', 'locationbitsmigrationdonesofar'));
        $this->assertSame(5, (int) get_config('tool_objectfs', 'locationbitsmigrationtotal'));
        foreach ($ids as $id) {
            $this->assert_row_consistent($id);
        }
        $this->assertTrue(location_query::uses_bits());
    }

    /**
     * Progress left over from a previous pass is discarded when the cursor is zero.
     */
    public function test_stale_progress_reset_on_fresh_pass(): void {
        $this->insert_object('a', OBJECT_LOCATION_LOCAL, 10);
        $this->insert_object('b', OBJECT_LOCATION_LOCAL, 10);
        set_config('locationbitsmigrationdonesofar', 99, 'tool_objectfs');

        $this->run_task();

        $this->assertSame(2, (int) get_config('tool_objectfs', 'locationbitsmigrationdonesofar'));
    }

    /**
     * Rows missed by the ID pass are repaired before the completion flag is enabled.
     */
    public function test_consistency_repair_before_completion(): void {
        // Rows with unset bits, wrong bits and a missing filesize, all behind the saved cursor.
        $unset = $this->insert_object('unset', OBJECT_LOCATION_DUPLICATED, 10);
        $wrong = $this->insert_object('wrong', OBJECT_LOCATION_LOCAL, 10, [0, 1, 1]);
        $nosize = $this->insert_object('nosize', OBJECT_LOCATION_EXTERNAL);
        $this->insert_file('nosize', 70);
        $orphaned = $this->insert_object('orphaned', OBJECT_LOCATION_ORPHANED, 10, [1, 1, 1]);
        $correct = $this->insert_object('correct', OBJECT_LOCATION_ERROR, 10, [0, 1, 0]);

        set_config('locationbitsmigrationlastid', $correct, 'tool_objectfs');
        set_config('locationbitsmigrationtotal', 5, 'tool_objectfs');
        set_config('locationbitsmigrationdonesofar', 5, 'tool_objectfs');

        $this->assertFalse(location_query::uses_bits());
        $this->run_task();

        // Nothing was left for the ID pass, so everything below was fixed by the repair step.
        $this->assertSame(5, (int) get_config('tool_objectfs', 'locationbitsmigrationdonesofar'));
        foreach ([$unset, $wrong, $nosize, $orphaned, $correct] as $id) {
            $this->assert_row_consistent($id);
        }
        $this->assertSame(70, $this->get_filesize($nosize));
        $this->assertTrue(location_query::uses_bits());
    }

    /**
     * Running again after the flag is reset re-verifies and re-enables it.
     */
    public function test_rerun_after_reset(): void {
        $id = $this->insert_object('a', OBJECT_LOCATION_EXTERNAL, 10);
        $this->run_task();
        $this->assertTrue(location_query::uses_bits());

        // Same state the reset CLI script leaves behind.
        set_config('locationbitsmigrationcomplete', 0, 'tool_objectfs');
        set_config('locationbitsmigrationlastid', 0, 'tool_objectfs');
        set_config('locationbitsmigrationtotal', 1, 'tool_objectfs');
        set_config('locationbitsmigrationdonesofar', 0, 'tool_objectfs');
        $this->assertFalse(location_query::uses_bits());

        $this->run_task();

        $this->assert_row_consistent($id);
        $this->assertTrue(location_query::uses_bits());
    }
}
