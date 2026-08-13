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

namespace tool_objectfs\hook;

use core\hook\described_hook;

/**
 * Hook dispatched to collect additional location bit-to-column mappings from sub-plugins.
 *
 * Listeners should call add_bit() to register their store columns on the
 * tool_objectfs_objects table. Each bit must be a unique power of 2 and must not
 * conflict with the base bits (1, 2, 4).
 *
 * @package   tool_objectfs
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_additional_location_bits implements described_hook {
    /** @var array Additional bit => column mappings registered by listeners. */
    private array $additionalbits = [];

    /**
     * Register an additional store bit column.
     *
     * @param int $bit The bit value (must be a power of 2, >= 8).
     * @param string $column The DB column name on tool_objectfs_objects.
     */
    public function add_bit(int $bit, string $column): void {
        // Must be a unique power of 2, >= 8, and must not conflict with the base bits (1, 2, 4).
        if ($bit < 8 || ($bit & ($bit - 1)) !== 0) {
            throw new \coding_exception('Invalid additional location bit: ' . $bit);
        }
        if ($column === '') {
            throw new \coding_exception('Additional location column name must not be empty');
        }
        if (!preg_match('/^[a-z][a-z0-9_]{0,29}$/', $column)) {
            throw new \coding_exception(
                'Invalid column name "' . $column . '": must be lowercase alphanumeric/underscore, '
                . 'start with a letter, and be at most 30 characters'
            );
        }
        if (isset($this->additionalbits[$bit])) {
            throw new \coding_exception('Additional location bit already registered: ' . $bit);
        }
        $this->additionalbits[$bit] = $column;
    }

    /**
     * Get all registered additional bit mappings.
     *
     * @return array [bit_value => column_name, ...]
     */
    public function get_additional_bits(): array {
        return $this->additionalbits;
    }

    /**
     * Describes the hook purpose.
     *
     * @return string
     */
    public static function get_hook_description(): string {
        return 'Allows plugins to register additional store-bit columns on the tool_objectfs_objects table '
            . 'for multi-store tracking. Each registered bit extends the location bitmask.';
    }

    /**
     * List of tags that describe this hook.
     *
     * @return string[]
     */
    public static function get_hook_tags(): array {
        return ['objectfs', 'store', 'location'];
    }
}
