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

require_once(__DIR__ . '/../../lib.php');

/**
 * Builds location conditions for the legacy and bit-column representations.
 *
 * @package    tool_objectfs
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class location_query {
    /**
     * Whether application queries should use the migrated bit columns.
     *
     * @return bool
     */
    public static function uses_bits(): bool {
        return (bool) get_config('tool_objectfs', 'locationbitsmigrationcomplete');
    }

    /**
     * Get a SQL condition for a location value.
     *
     * @param int $location Legacy location value.
     * @param string $alias Optional table alias.
     * @return string
     */
    public static function condition(int $location, string $alias = ''): string {
        if (!self::uses_bits()) {
            $prefix = $alias === '' ? '' : $alias . '.';
            return $prefix . 'location = ' . $location;
        }

        return self::bit_condition($location, $alias);
    }

    /**
     * Get the bit-column values for a location value.
     *
     * @param int $location Legacy location value.
     * @return array{int, int, int}
     */
    public static function bits(int $location): array {
        return [
            OBJECT_LOCATION_ORPHANED => [1, 0, 0],
            OBJECT_LOCATION_ERROR => [0, 1, 0],
            OBJECT_LOCATION_LOCAL => [1, 1, 0],
            OBJECT_LOCATION_DUPLICATED => [1, 1, 1],
            OBJECT_LOCATION_EXTERNAL => [0, 1, 1],
        ][$location];
    }

    /**
     * Get a SQL condition for bit columns, regardless of migration state.
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

    /**
     * Check whether an object has the expected bit-column values.
     *
     * @param \stdClass $object Object record.
     * @param int $location Legacy location value.
     * @return bool
     */
    public static function matches(\stdClass $object, int $location): bool {
        [$infiledir, $inmdlfiles, $inremote] = self::bits($location);
        return isset($object->in_filedir, $object->in_mdl_files, $object->in_remote)
                && (int) $object->in_filedir === $infiledir
                && (int) $object->in_mdl_files === $inmdlfiles
                && (int) $object->in_remote === $inremote;
    }

    /**
     * Get the condition for an object not yet present in the objects table.
     *
     * @param string $alias Table alias.
     * @return string
     */
    public static function missing(string $alias): string {
        return self::uses_bits() ? $alias . '.id IS NULL' : $alias . '.location IS NULL';
    }
}
