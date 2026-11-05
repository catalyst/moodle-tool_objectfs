<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace tool_objectfs\local;

/**
 * Tests for location query helpers.
 *
 * @covers \tool_objectfs\local\location_query
 * @package tool_objectfs
 * @copyright Catalyst IT
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class location_query_test extends \tool_objectfs\tests\testcase {
    /**
     * Location values and their expected bit-column representations.
     *
     * @return array
     */
    public static function location_bits_provider(): array {
        return [
            'orphaned' => [-2, [1, 0, 0]],
            'error' => [-1, [0, 1, 0]],
            'local' => [0, [1, 1, 0]],
            'duplicated' => [1, [1, 1, 1]],
            'external' => [2, [0, 1, 1]],
        ];
    }

    /**
     * Test location values map to their bit-column values.
     *
     * @dataProvider location_bits_provider
     * @param int $location Location value.
     * @param array $expected Expected bit values.
     */
    public function test_bits(int $location, array $expected): void {
        $this->assertSame($expected, location_query::bits($location));
    }

    /**
     * Test the bit-column SQL condition, with and without an alias.
     *
     * @dataProvider location_bits_provider
     * @param int $location Legacy location value.
     * @param array $bits Expected bit values.
     */
    public function test_bit_condition(int $location, array $bits): void {
        $expected = sprintf('in_filedir = %d AND in_mdl_files = %d AND in_remote = %d', ...$bits);
        $expectedaliased = sprintf('o.in_filedir = %d AND o.in_mdl_files = %d AND o.in_remote = %d', ...$bits);
        $this->assertSame($expected, location_query::bit_condition($location));
        $this->assertSame($expectedaliased, location_query::bit_condition($location, 'o'));
    }
}
