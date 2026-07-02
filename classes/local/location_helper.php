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
 * Helper for converting between bitmask location values and individual DB columns.
 *
 * The PHP layer works with bitmask integers (OBJECT_LOCATION_IN_FILEDIR | OBJECT_LOCATION_IN_MDL_FILES etc.)
 * while the DB stores individual boolean columns (in_filedir, in_mdl_files, in_remote).
 *
 * @package   tool_objectfs
 * @author    Catalyst IT
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\local;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../lib.php');

/**
 * Translates between the PHP bitmask representation and the DB boolean columns.
 */
class location_helper {
    /**
     * Get the registry mapping each bit flag constant to its corresponding DB column name.
     * Must be a method rather than a class constant because OBJECT_LOCATION_* are defined at runtime.
     *
     * @return array [bit_value => column_name, ...]
     */
    public static function get_bit_columns(): array {
        return [
            OBJECT_LOCATION_IN_FILEDIR => 'in_filedir',
            OBJECT_LOCATION_IN_MDL_FILES => 'in_mdl_files',
            OBJECT_LOCATION_IN_REMOTE => 'in_remote',
        ];
    }

    /**
     * Convert a bitmask to an associative array of column => 0/1 values for DB storage.
     *
     * @param int $location Bitmask (e.g. OBJECT_LOCATION_DUPLICATED = 7).
     * @return array ['in_filedir' => 1, 'in_mdl_files' => 1, 'in_remote' => 1]
     */
    public static function bits_to_columns(int $location): array {
        $columns = [];
        foreach (self::get_bit_columns() as $bit => $column) {
            $columns[$column] = ($location & $bit) ? 1 : 0;
        }
        return $columns;
    }

    /**
     * Reconstruct a bitmask from a DB row's boolean columns.
     *
     * @param object $row A DB record containing in_filedir, in_mdl_files, in_remote fields.
     * @return int The composed bitmask.
     */
    public static function columns_to_bits(object $row): int {
        $location = 0;
        foreach (self::get_bit_columns() as $bit => $column) {
            if (!empty($row->$column)) {
                $location |= $bit;
            }
        }
        return $location;
    }

    /**
     * Generate SQL WHERE conditions for bitmask-based candidate queries.
     *
     * Converts has_mask/not_mask into column equality checks.
     * Example: has_mask=3 (IN_FILEDIR|IN_MDL_FILES), not_mask=4 (IN_REMOTE)
     *   → "in_filedir = 1 AND in_mdl_files = 1 AND in_remote = 0"
     *
     * @param int $hasmask Bits that MUST be set.
     * @param int $notmask Bits that must NOT be set.
     * @return string SQL conditions (without leading WHERE/AND).
     */
    public static function bits_to_sql_conditions(int $hasmask, int $notmask): string {
        $conditions = [];
        foreach (self::get_bit_columns() as $bit => $column) {
            if ($hasmask & $bit) {
                $conditions[] = $column . ' = 1';
            }
            if ($notmask & $bit) {
                $conditions[] = $column . ' = 0';
            }
        }
        return implode(' AND ', $conditions);
    }

    /**
     * Generate SQL WHERE conditions with a table alias prefix.
     *
     * @param int $hasmask Bits that MUST be set.
     * @param int $notmask Bits that must NOT be set.
     * @param string $alias Table alias (e.g. 'o').
     * @return string SQL conditions with alias prefix.
     */
    public static function bits_to_sql_conditions_aliased(int $hasmask, int $notmask, string $alias): string {
        $conditions = [];
        foreach (self::get_bit_columns() as $bit => $column) {
            if ($hasmask & $bit) {
                $conditions[] = $alias . '.' . $column . ' = 1';
            }
            if ($notmask & $bit) {
                $conditions[] = $alias . '.' . $column . ' = 0';
            }
        }
        return implode(' AND ', $conditions);
    }

    /**
     * Get the column name for a given bit flag.
     *
     * @param int $bit A single bit flag constant.
     * @return string|null Column name or null if not registered.
     */
    public static function get_column_for_bit(int $bit): ?string {
        return self::get_bit_columns()[$bit] ?? null;
    }

    /**
     * Generate SQL WHERE conditions for an exact location match.
     *
     * Unlike bits_to_sql_conditions which only checks specified has/not bits,
     * this checks ALL registered bits - those in the bitmask must be 1, those not in it must be 0.
     *
     * @param int $location The exact bitmask to match.
     * @param string $alias Optional table alias prefix.
     * @return string SQL conditions.
     */
    public static function bits_to_exact_sql(int $location, string $alias = ''): string {
        $prefix = $alias !== '' ? $alias . '.' : '';
        $conditions = [];
        foreach (self::get_bit_columns() as $bit => $column) {
            $conditions[] = $prefix . $column . ' = ' . (($location & $bit) ? '1' : '0');
        }
        return implode(' AND ', $conditions);
    }
}
