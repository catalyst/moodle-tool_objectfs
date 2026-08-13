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

/**
 * Unit tests for location_helper bitmask methods.
 *
 * @covers \tool_objectfs\local\location_helper
 * @package   tool_objectfs
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class location_helper_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        // Ensure the constants are loaded.
        global $CFG;
        require_once($CFG->dirroot . '/admin/tool/objectfs/lib.php');
    }

    // Tests for has_bit.

    public function test_has_bit_single_bit_present(): void {
        $this->assertTrue(location_helper::has_bit(OBJECT_LOCATION_DUPLICATED, OBJECT_LOCATION_IN_FILEDIR));
        $this->assertTrue(location_helper::has_bit(OBJECT_LOCATION_DUPLICATED, OBJECT_LOCATION_IN_MDL_FILES));
        $this->assertTrue(location_helper::has_bit(OBJECT_LOCATION_DUPLICATED, OBJECT_LOCATION_IN_REMOTE));
    }

    public function test_has_bit_single_bit_absent(): void {
        $this->assertFalse(location_helper::has_bit(OBJECT_LOCATION_LOCAL, OBJECT_LOCATION_IN_REMOTE));
        $this->assertFalse(location_helper::has_bit(OBJECT_LOCATION_EXTERNAL, OBJECT_LOCATION_IN_FILEDIR));
    }

    public function test_has_bit_combined_mask_all_present(): void {
        $mask = OBJECT_LOCATION_IN_FILEDIR | OBJECT_LOCATION_IN_MDL_FILES;
        $this->assertTrue(location_helper::has_bit(OBJECT_LOCATION_DUPLICATED, $mask));
    }

    public function test_has_bit_combined_mask_partial_present(): void {
        // LOCAL has IN_FILEDIR | IN_MDL_FILES but not IN_REMOTE.
        $mask = OBJECT_LOCATION_IN_FILEDIR | OBJECT_LOCATION_IN_REMOTE;
        $this->assertFalse(location_helper::has_bit(OBJECT_LOCATION_LOCAL, $mask));
    }

    public function test_has_bit_zero_location(): void {
        $this->assertFalse(location_helper::has_bit(OBJECT_LOCATION_ERROR, OBJECT_LOCATION_IN_FILEDIR));
    }

    public function test_has_bit_zero_mask(): void {
        // Checking for zero bits should always be true (vacuously).
        $this->assertTrue(location_helper::has_bit(OBJECT_LOCATION_LOCAL, 0));
    }

    // Tests for lacks_bit.

    public function test_lacks_bit_single_bit_absent(): void {
        $this->assertTrue(location_helper::lacks_bit(OBJECT_LOCATION_LOCAL, OBJECT_LOCATION_IN_REMOTE));
    }

    public function test_lacks_bit_single_bit_present(): void {
        $this->assertFalse(location_helper::lacks_bit(OBJECT_LOCATION_DUPLICATED, OBJECT_LOCATION_IN_REMOTE));
    }

    public function test_lacks_bit_combined_mask_none_present(): void {
        // MISSING = IN_MDL_FILES only. Lacks both IN_FILEDIR and IN_REMOTE.
        $mask = OBJECT_LOCATION_IN_FILEDIR | OBJECT_LOCATION_IN_REMOTE;
        $this->assertTrue(location_helper::lacks_bit(OBJECT_LOCATION_MISSING, $mask));
    }

    public function test_lacks_bit_combined_mask_one_present(): void {
        // LOCAL = IN_FILEDIR | IN_MDL_FILES. Checking for lacks (IN_FILEDIR | IN_REMOTE) should be false
        // because IN_FILEDIR IS present.
        $mask = OBJECT_LOCATION_IN_FILEDIR | OBJECT_LOCATION_IN_REMOTE;
        $this->assertFalse(location_helper::lacks_bit(OBJECT_LOCATION_LOCAL, $mask));
    }

    public function test_lacks_bit_zero_location(): void {
        $this->assertTrue(location_helper::lacks_bit(OBJECT_LOCATION_ERROR, OBJECT_LOCATION_IN_REMOTE));
    }

    // Tests for is_local.

    public function test_is_local_with_local_location(): void {
        $this->assertTrue(location_helper::is_local(OBJECT_LOCATION_LOCAL));
    }

    public function test_is_local_with_duplicated_location(): void {
        // DUPLICATED has IN_REMOTE set, so not local.
        $this->assertFalse(location_helper::is_local(OBJECT_LOCATION_DUPLICATED));
    }

    public function test_is_local_with_external_location(): void {
        $this->assertFalse(location_helper::is_local(OBJECT_LOCATION_EXTERNAL));
    }

    public function test_is_local_with_missing_location(): void {
        $this->assertFalse(location_helper::is_local(OBJECT_LOCATION_MISSING));
    }

    public function test_is_local_with_error_location(): void {
        $this->assertFalse(location_helper::is_local(OBJECT_LOCATION_ERROR));
    }

    public function test_is_local_with_orphaned_location(): void {
        // ORPHANED = IN_FILEDIR only (no IN_MDL_FILES), so not local.
        $this->assertFalse(location_helper::is_local(OBJECT_LOCATION_ORPHANED));
    }

    // Tests for is_external.

    public function test_is_external_with_external_location(): void {
        $this->assertTrue(location_helper::is_external(OBJECT_LOCATION_EXTERNAL));
    }

    public function test_is_external_with_duplicated_location(): void {
        // DUPLICATED has IN_FILEDIR set, so not strictly external.
        $this->assertFalse(location_helper::is_external(OBJECT_LOCATION_DUPLICATED));
    }

    public function test_is_external_with_local_location(): void {
        $this->assertFalse(location_helper::is_external(OBJECT_LOCATION_LOCAL));
    }

    public function test_is_external_with_missing_location(): void {
        $this->assertFalse(location_helper::is_external(OBJECT_LOCATION_MISSING));
    }

    public function test_is_external_with_error_location(): void {
        $this->assertFalse(location_helper::is_external(OBJECT_LOCATION_ERROR));
    }

    // Tests for is_duplicated.

    public function test_is_duplicated_with_duplicated_location(): void {
        $this->assertTrue(location_helper::is_duplicated(OBJECT_LOCATION_DUPLICATED));
    }

    public function test_is_duplicated_with_local_location(): void {
        $this->assertFalse(location_helper::is_duplicated(OBJECT_LOCATION_LOCAL));
    }

    public function test_is_duplicated_with_external_location(): void {
        $this->assertFalse(location_helper::is_duplicated(OBJECT_LOCATION_EXTERNAL));
    }

    public function test_is_duplicated_with_error_location(): void {
        $this->assertFalse(location_helper::is_duplicated(OBJECT_LOCATION_ERROR));
    }

    // Tests for is_missing.

    public function test_is_missing_with_missing_location(): void {
        $this->assertTrue(location_helper::is_missing(OBJECT_LOCATION_MISSING));
    }

    public function test_is_missing_with_local_location(): void {
        // LOCAL has IN_FILEDIR set, so not missing.
        $this->assertFalse(location_helper::is_missing(OBJECT_LOCATION_LOCAL));
    }

    public function test_is_missing_with_external_location(): void {
        // EXTERNAL has IN_REMOTE set, so not missing.
        $this->assertFalse(location_helper::is_missing(OBJECT_LOCATION_EXTERNAL));
    }

    public function test_is_missing_with_duplicated_location(): void {
        $this->assertFalse(location_helper::is_missing(OBJECT_LOCATION_DUPLICATED));
    }

    public function test_is_missing_with_error_location(): void {
        // ERROR = 0, lacks IN_MDL_FILES so is_missing returns false.
        $this->assertFalse(location_helper::is_missing(OBJECT_LOCATION_ERROR));
    }

    // Tests for is_in_remote.

    public function test_is_in_remote_with_external_location(): void {
        $this->assertTrue(location_helper::is_in_remote(OBJECT_LOCATION_EXTERNAL));
    }

    public function test_is_in_remote_with_duplicated_location(): void {
        $this->assertTrue(location_helper::is_in_remote(OBJECT_LOCATION_DUPLICATED));
    }

    public function test_is_in_remote_with_local_location(): void {
        $this->assertFalse(location_helper::is_in_remote(OBJECT_LOCATION_LOCAL));
    }

    public function test_is_in_remote_with_missing_location(): void {
        $this->assertFalse(location_helper::is_in_remote(OBJECT_LOCATION_MISSING));
    }

    public function test_is_in_remote_with_error_location(): void {
        $this->assertFalse(location_helper::is_in_remote(OBJECT_LOCATION_ERROR));
    }

    public function test_is_in_remote_with_bare_remote_bit(): void {
        // Just IN_REMOTE (no MDL_FILES) — still counts as in_remote.
        $this->assertTrue(location_helper::is_in_remote(OBJECT_LOCATION_IN_REMOTE));
    }

    // Tests for bits_to_columns and columns_to_bits round-trip.

    /**
     * Test that bits_to_columns and columns_to_bits round-trip correctly.
     *
     * @dataProvider location_round_trip_provider
     */
    public function test_bits_to_columns_and_back(int $location, array $expectedcolumns): void {
        $columns = location_helper::bits_to_columns($location);
        // Check the base three columns match.
        foreach ($expectedcolumns as $col => $val) {
            $this->assertSame($val, $columns[$col], "Column $col mismatch for location $location");
        }

        // Round-trip: columns_to_bits should reconstruct the same bitmask.
        $row = (object) $columns;
        $this->assertSame($location, location_helper::columns_to_bits($row));
    }

    /**
     * Data provider for round-trip conversion tests.
     *
     * @return array
     */
    public static function location_round_trip_provider(): array {
        return [
            'error (0)' => [
                0,
                ['in_filedir' => 0, 'in_mdl_files' => 0, 'in_remote' => 0],
            ],
            'orphaned (1)' => [
                1, // IN_FILEDIR only.
                ['in_filedir' => 1, 'in_mdl_files' => 0, 'in_remote' => 0],
            ],
            'missing (2)' => [
                2, // IN_MDL_FILES only.
                ['in_filedir' => 0, 'in_mdl_files' => 1, 'in_remote' => 0],
            ],
            'local (3)' => [
                3, // IN_FILEDIR | IN_MDL_FILES.
                ['in_filedir' => 1, 'in_mdl_files' => 1, 'in_remote' => 0],
            ],
            'remote only (4)' => [
                4, // IN_REMOTE only.
                ['in_filedir' => 0, 'in_mdl_files' => 0, 'in_remote' => 1],
            ],
            'external (6)' => [
                6, // IN_MDL_FILES | IN_REMOTE.
                ['in_filedir' => 0, 'in_mdl_files' => 1, 'in_remote' => 1],
            ],
            'duplicated (7)' => [
                7, // IN_FILEDIR | IN_MDL_FILES | IN_REMOTE.
                ['in_filedir' => 1, 'in_mdl_files' => 1, 'in_remote' => 1],
            ],
        ];
    }

    // Tests for mutual exclusivity of named states.

    /**
     * Verify that each canonical location matches exactly one named state predicate.
     *
     * @dataProvider canonical_state_provider
     */
    public function test_exactly_one_state_predicate_matches(int $location, string $expectedstate): void {
        $states = [
            'local' => location_helper::is_local($location),
            'external' => location_helper::is_external($location),
            'duplicated' => location_helper::is_duplicated($location),
            'missing' => location_helper::is_missing($location),
        ];

        $this->assertTrue($states[$expectedstate], "$expectedstate should be true for location $location");
        unset($states[$expectedstate]);
        foreach ($states as $name => $value) {
            $this->assertFalse($value, "$name should be false for location $location (expected $expectedstate)");
        }
    }

    /**
     * Data provider for canonical state predicate tests.
     *
     * @return array
     */
    public static function canonical_state_provider(): array {
        global $CFG;
        require_once($CFG->dirroot . '/admin/tool/objectfs/lib.php');
        return [
            'local' => [OBJECT_LOCATION_LOCAL, 'local'],
            'external' => [OBJECT_LOCATION_EXTERNAL, 'external'],
            'duplicated' => [OBJECT_LOCATION_DUPLICATED, 'duplicated'],
            'missing' => [OBJECT_LOCATION_MISSING, 'missing'],
        ];
    }
}
