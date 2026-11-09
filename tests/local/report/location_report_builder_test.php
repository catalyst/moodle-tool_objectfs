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

/**
 * Location report builder tests.
 *
 * @package   tool_objectfs
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\local\report;

use tool_objectfs\local\location_query;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../../lib.php');

/**
 * Location report builder tests, with the location bits migration flag off and on.
 *
 * @covers \tool_objectfs\local\report\location_report_builder
 */
final class location_report_builder_test extends \tool_objectfs\tests\testcase {
    /**
     * Start from empty tables and no configured filesystem, so the filedir stats are deterministic (always 0).
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $DB->delete_records('files');
        $DB->delete_records('tool_objectfs_objects');
        set_config('filesystem', '', 'tool_objectfs');
    }

    /**
     * Insert a row in {files}.
     *
     * @param string $name Unique name, the content hash is derived from it.
     * @param int $filesize Size recorded in {files}.
     */
    private function insert_file(string $name, int $filesize): void {
        global $DB;
        $DB->insert_record('files', (object) [
            'contenthash' => sha1($name),
            'pathnamehash' => sha1('/' . $name),
            'contextid' => \context_system::instance()->id,
            'component' => 'core',
            'filearea' => 'unittest',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $name,
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
     * Insert a row in {tool_objectfs_objects} with both the legacy location and the location bits populated.
     *
     * @param string $name Name matching the one given to insert_file(), if any.
     * @param int $location OBJECT_LOCATION_* constant.
     * @param int|null $filesize Size recorded in the objects table.
     */
    private function insert_object(string $name, int $location, ?int $filesize): void {
        global $DB;
        [$infiledir, $inmdlfiles, $inremote] = location_query::bits($location);
        $DB->insert_record('tool_objectfs_objects', (object) [
            'contenthash' => sha1($name),
            'timeduplicated' => 0,
            'location' => $location,
            'filesize' => $filesize,
            'in_filedir' => $infiledir,
            'in_mdl_files' => $inmdlfiles,
            'in_remote' => $inremote,
        ]);
    }

    /**
     * Create the fixtures covering ordinary, zero-size, NULL-size, orphaned and object-less files.
     */
    private function create_fixtures(): void {
        // Ordinary files, with the size in both tables.
        $this->insert_file('local', 100);
        $this->insert_object('local', OBJECT_LOCATION_LOCAL, 100);
        $this->insert_file('duplicated', 300);
        $this->insert_object('duplicated', OBJECT_LOCATION_DUPLICATED, 300);
        $this->insert_file('external', 400);
        $this->insert_object('external', OBJECT_LOCATION_EXTERNAL, 400);
        $this->insert_file('error', 500);
        $this->insert_object('error', OBJECT_LOCATION_ERROR, 500);

        // Zero-size files are excluded from every location except orphaned.
        $this->insert_file('local-zero', 0);
        $this->insert_object('local-zero', OBJECT_LOCATION_LOCAL, 0);
        $this->insert_file('duplicated-zero', 0);
        $this->insert_object('duplicated-zero', OBJECT_LOCATION_DUPLICATED, 0);
        $this->insert_file('external-zero', 0);
        $this->insert_object('external-zero', OBJECT_LOCATION_EXTERNAL, 0);

        // Object size not yet populated (NULL), but the file exists in {files} with a size.
        $this->insert_file('local-nullsize', 50);
        $this->insert_object('local-nullsize', OBJECT_LOCATION_LOCAL, null);
        $this->insert_file('external-nullsize', 60);
        $this->insert_object('external-nullsize', OBJECT_LOCATION_EXTERNAL, null);

        // File in {files} that the checker has not yet given an object row.
        $this->insert_file('local-noobject', 200);

        // Object row whose file is no longer in {files}, not orphaned.
        $this->insert_object('external-nofile', OBJECT_LOCATION_EXTERNAL, 70);

        // Orphaned objects have no {files} row; their size is never reported.
        $this->insert_object('orphaned', OBJECT_LOCATION_ORPHANED, 600);
        $this->insert_object('orphaned-zero', OBJECT_LOCATION_ORPHANED, 0);
        $this->insert_object('orphaned-nullsize', OBJECT_LOCATION_ORPHANED, null);
    }

    /**
     * Build the location report and flatten it to [datakey => [count, sum]].
     *
     * @return array
     */
    private function build_rows(): array {
        $rows = [];
        foreach ((new location_report_builder())->build_report(1)->get_rows() as $row) {
            $rows[$row->datakey] = [(int) $row->objectcount, (int) $row->objectsum];
        }
        return $rows;
    }

    /**
     * Expected report per migration state.
     *
     * Legacy joins {files} (sizes come from there); bits reads only the objects table. So bits skips
     * files without an object row and counts objects without a file, and a NULL object size adds 0.
     *
     * @return array
     */
    public static function report_provider(): array {
        return [
            'migration flag off (legacy location column)' => [
                0,
                [
                    OBJECT_LOCATION_LOCAL => [3, 350],
                    OBJECT_LOCATION_DUPLICATED => [1, 300],
                    OBJECT_LOCATION_EXTERNAL => [2, 460],
                    OBJECT_LOCATION_ORPHANED => [3, 0],
                    OBJECT_LOCATION_ERROR => [1, 500],
                    'total' => [10, 1610],
                    'filedir' => [0, 0],
                    'deltab' => [4, 650],
                ],
            ],
            'migration flag on (location bits)' => [
                1,
                [
                    OBJECT_LOCATION_LOCAL => [2, 100],
                    OBJECT_LOCATION_DUPLICATED => [1, 300],
                    OBJECT_LOCATION_EXTERNAL => [3, 470],
                    OBJECT_LOCATION_ORPHANED => [3, 0],
                    OBJECT_LOCATION_ERROR => [1, 500],
                    'total' => [10, 1370],
                    'filedir' => [0, 0],
                    'deltab' => [3, 400],
                ],
            ],
        ];
    }

    /**
     * Test per-location counts and sizes, totals and the filedir delta.
     *
     * @dataProvider report_provider
     * @param int $migrationcomplete Value of the locationbitsmigrationcomplete flag.
     * @param array $expected Expected [count, sum] by datakey.
     */
    public function test_build_report(int $migrationcomplete, array $expected): void {
        set_config('locationbitsmigrationcomplete', $migrationcomplete, 'tool_objectfs');
        $this->create_fixtures();

        $this->assertEquals($expected, $this->build_rows());
    }

    /**
     * Test the report on an empty objects table in both states.
     */
    public function test_build_report_empty(): void {
        $expected = [
            OBJECT_LOCATION_LOCAL => [0, 0],
            OBJECT_LOCATION_DUPLICATED => [0, 0],
            OBJECT_LOCATION_EXTERNAL => [0, 0],
            OBJECT_LOCATION_ORPHANED => [0, 0],
            OBJECT_LOCATION_ERROR => [0, 0],
            'total' => [0, 0],
            'filedir' => [0, 0],
            'deltaa' => [0, 0],
        ];
        foreach ([0, 1] as $migrationcomplete) {
            set_config('locationbitsmigrationcomplete', $migrationcomplete, 'tool_objectfs');
            $this->assertEquals($expected, $this->build_rows(), "flag {$migrationcomplete}");
        }
    }
}
