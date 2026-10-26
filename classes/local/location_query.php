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

defined('MOODLE_INTERNAL') || die();

/**
 * Maps legacy location values (the dropped `location` column) to the bit columns, for the async migration.
 *
 * The OBJECT_LOCATION_* constants are now bitmasks, so legacy values must not use them.
 *
 * @package    tool_objectfs
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class location_query {
    /** @var int Legacy value for objects only in filedir. */
    const LEGACY_ORPHANED = -2;

    /** @var int Legacy value for objects only in {files}. */
    const LEGACY_ERROR = -1;

    /** @var int Legacy value for objects in filedir and {files}. */
    const LEGACY_LOCAL = 0;

    /** @var int Legacy value for objects in filedir, {files} and remote. */
    const LEGACY_DUPLICATED = 1;

    /** @var int Legacy value for objects in {files} and remote. */
    const LEGACY_EXTERNAL = 2;

    /** @var int[] All legacy location values. */
    const LEGACY_LOCATIONS = [
        self::LEGACY_ORPHANED,
        self::LEGACY_ERROR,
        self::LEGACY_LOCAL,
        self::LEGACY_DUPLICATED,
        self::LEGACY_EXTERNAL,
    ];

    /**
     * Get the bit-column values for a legacy location value.
     *
     * @param int $location Legacy location value.
     * @return array{int, int, int} in_filedir, in_mdl_files, in_remote.
     */
    public static function bits(int $location): array {
        return [
            self::LEGACY_ORPHANED => [1, 0, 0],
            self::LEGACY_ERROR => [0, 1, 0],
            self::LEGACY_LOCAL => [1, 1, 0],
            self::LEGACY_DUPLICATED => [1, 1, 1],
            self::LEGACY_EXTERNAL => [0, 1, 1],
        ][$location];
    }

    /**
     * Get a SQL condition on the bit columns for a legacy location value.
     *
     * @param int $location Legacy location value.
     * @param string $alias Optional table alias.
     * @return string
     */
    public static function bit_condition(int $location, string $alias = ''): string {
        $prefix = $alias === '' ? '' : $alias . '.';
        $bits = self::bits($location);

        return sprintf(
            '%sin_filedir = %d AND %sin_mdl_files = %d AND %sin_remote = %d',
            $prefix,
            $bits[0],
            $prefix,
            $bits[1],
            $prefix,
            $bits[2]
        );
    }
}
