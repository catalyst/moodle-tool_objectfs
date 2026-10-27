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
use tool_objectfs\local\location_query;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../lib.php');

/**
 * Ad-hoc task to migrate the legacy 'location' column on tool_objectfs_objects to the
 * in_filedir/in_mdl_files/in_remote boolean columns, and to backfill missing filesize values,
 * in small batches. Used for async upgrade.
 *
 * @package    tool_objectfs
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class migrate_object_locations extends adhoc_task {
    /** @var \core\output\stored_progress_bar|null */
    protected $progress = null;

    /** @var int Number of rows migrated per iteration. The filesize backfill does a {files} lookup per row. */
    const BATCH_SIZE = 100000;

    /**
     * Start stored progress when supported by the running Moodle version.
     */
    private function start_optional_stored_progress(): void {
        if (!class_exists(\core\output\stored_progress_bar::class)) {
            return;
        }

        global $OUTPUT, $PAGE;

        $OUTPUT = $PAGE->get_renderer('core', null, 'cli');
        $name = get_class($this) . '_' . $this->get_id();
        $this->progress = new \core\output\stored_progress_bar(
            \core\output\stored_progress_bar::convert_to_idnumber($name)
        );
        $this->progress->start();
    }

    /**
     * Action of task.
     */
    public function execute() {
        global $DB;

        $lastid = (int) get_config('tool_objectfs', 'locationbitsmigrationlastid');
        $total = (int) get_config('tool_objectfs', 'locationbitsmigrationtotal');
        $donesofar = (int) get_config('tool_objectfs', 'locationbitsmigrationdonesofar');

        // A zero cursor marks a fresh migration pass, so never carry progress over from a previous run.
        if ($lastid === 0) {
            $donesofar = 0;
            set_config('locationbitsmigrationdonesofar', 0, 'tool_objectfs');
        }

        $this->start_optional_stored_progress();
        if ($total === 0) {
            $total = $DB->count_records('tool_objectfs_objects');
            set_config('locationbitsmigrationtotal', $total, 'tool_objectfs');
        }

        $iteration = 0;
        do {
            $iteration++;

            // Find the id marking the end of this batch without loading rows into PHP.
            $boundary = $DB->get_records_select(
                'tool_objectfs_objects',
                'id > :lastid',
                ['lastid' => $lastid],
                'id ASC',
                'id',
                self::BATCH_SIZE - 1,
                1
            );

            if (!empty($boundary)) {
                $upperid = (int) reset($boundary)->id;
                $processed = self::BATCH_SIZE;
            } else {
                // Fewer than BATCH_SIZE rows remain; process the final delta.
                $upperid = (int) $DB->get_field_select(
                    'tool_objectfs_objects',
                    'MAX(id)',
                    'id > :lastid',
                    ['lastid' => $lastid]
                );
                $processed = $upperid > 0
                    ? $DB->count_records_select('tool_objectfs_objects', 'id > :lastid', ['lastid' => $lastid])
                    : 0;
            }

            // Set-based update per legacy location value, scoped to this ID range.
            if ($upperid > $lastid) {
                $locations = [
                    \OBJECT_LOCATION_ORPHANED => true,
                    \OBJECT_LOCATION_ERROR => true,
                    \OBJECT_LOCATION_LOCAL => true,
                    \OBJECT_LOCATION_DUPLICATED => true,
                    \OBJECT_LOCATION_EXTERNAL => true,
                ];
                foreach (array_keys($locations) as $location) {
                    [$infiledir, $inmdlfiles, $inremote] = location_query::bits($location);
                    $DB->execute(
                        'UPDATE {tool_objectfs_objects}
                            SET in_filedir = :infiledir, in_mdl_files = :inmdlfiles, in_remote = :inremote
                          WHERE location = :location AND id > :lastid AND id <= :upperid',
                        [
                            'infiledir' => $infiledir,
                            'inmdlfiles' => $inmdlfiles,
                            'inremote' => $inremote,
                            'location' => $location,
                            'lastid' => $lastid,
                            'upperid' => $upperid,
                        ]
                    );
                }

                // Bits are set above, so in_mdl_files = 1 selects objects that have a {files} row.
                $DB->execute(
                    'UPDATE {tool_objectfs_objects}
                        SET filesize = (SELECT MAX(f.filesize)
                                          FROM {files} f
                                         WHERE f.contenthash = {tool_objectfs_objects}.contenthash)
                      WHERE filesize IS NULL AND in_mdl_files = 1 AND id > :lastid AND id <= :upperid',
                    ['lastid' => $lastid, 'upperid' => $upperid]
                );
            }

            $donesofar += $processed;
            $lastid = $upperid;
            set_config('locationbitsmigrationlastid', $lastid, 'tool_objectfs');
            set_config('locationbitsmigrationdonesofar', $donesofar, 'tool_objectfs');
            $percent = $total > 0 ? round(($donesofar / $total) * 100, 2) : 100;

            if (!PHPUNIT_TEST) {
                mtrace("tool_objectfs location bits migration: {$donesofar}/{$total} ({$percent}%) complete.");
            }

            if ($this->progress !== null) {
                $this->progress->update($donesofar, $total, 'tool_objectfs location bits migration:');
            }

            if ($upperid !== 0) {
                continue;
            }

            // Migration complete, verify consistency of location bits.
            if ($this->count_inconsistent() > 0) {
                // Fix only the rows missed by the ID pass rather than rescanning from the start.
                $this->repair_inconsistent();
            }

            $inconsistent = $this->count_inconsistent();
            if ($inconsistent > 0) {
                set_config('locationbitsmigrationcomplete', 0, 'tool_objectfs');
                throw new \moodle_exception(
                    'generalexceptionmessage',
                    'error',
                    '',
                    "tool_objectfs location bits migration: {$inconsistent} rows still inconsistent after repair."
                );
            }

            set_config('locationbitsmigrationcomplete', 1, 'tool_objectfs');
            if (!PHPUNIT_TEST) {
                mtrace('tool_objectfs location bits migration complete and verified.');
            }
            break;
        } while (true);
    }

    /**
     * Legacy location values that have corresponding bit values.
     *
     * @return int[]
     */
    private static function locations(): array {
        return [
            \OBJECT_LOCATION_ORPHANED,
            \OBJECT_LOCATION_ERROR,
            \OBJECT_LOCATION_LOCAL,
            \OBJECT_LOCATION_DUPLICATED,
            \OBJECT_LOCATION_EXTERNAL,
        ];
    }

    /**
     * Count rows whose bits disagree with the legacy location, or whose filesize is missing despite a {files} row.
     *
     * @return int
     */
    private function count_inconsistent(): int {
        global $DB;

        $conditions = [];
        foreach (self::locations() as $location) {
            $conditions[] = '(location = ' . $location . ' AND NOT (' . location_query::bit_condition($location) . '))';
        }
        $inconsistent = $DB->count_records_select('tool_objectfs_objects', implode(' OR ', $conditions));

        // Objects whose file is gone legitimately keep a NULL filesize, so only count those with a {files} row.
        return $inconsistent + $DB->count_records_sql(
            'SELECT COUNT(1)
               FROM {tool_objectfs_objects} o
              WHERE o.filesize IS NULL AND o.in_mdl_files = 1
                AND EXISTS (SELECT 1 FROM {files} f WHERE f.contenthash = o.contenthash)'
        );
    }

    /**
     * Fix the bits and filesize on only the rows that count_inconsistent() reports.
     */
    private function repair_inconsistent(): void {
        global $DB;

        foreach (self::locations() as $location) {
            [$infiledir, $inmdlfiles, $inremote] = location_query::bits($location);
            $DB->execute(
                'UPDATE {tool_objectfs_objects}
                    SET in_filedir = :infiledir, in_mdl_files = :inmdlfiles, in_remote = :inremote
                  WHERE location = :location AND NOT (' . location_query::bit_condition($location) . ')',
                [
                    'infiledir' => $infiledir,
                    'inmdlfiles' => $inmdlfiles,
                    'inremote' => $inremote,
                    'location' => $location,
                ]
            );
        }

        $DB->execute(
            'UPDATE {tool_objectfs_objects}
                SET filesize = (SELECT MAX(f.filesize)
                                  FROM {files} f
                                 WHERE f.contenthash = {tool_objectfs_objects}.contenthash)
              WHERE filesize IS NULL AND in_mdl_files = 1
                AND EXISTS (SELECT 1 FROM {files} f2 WHERE f2.contenthash = {tool_objectfs_objects}.contenthash)'
        );
    }
}
