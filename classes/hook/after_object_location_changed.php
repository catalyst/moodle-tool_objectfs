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
use stdClass;

/**
 * Hook dispatched after an object's location changes in tool_objectfs_objects.
 *
 * Listeners can use this to maintain per-store placement records or trigger
 * additional actions based on location changes.
 *
 * @package   tool_objectfs
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class after_object_location_changed implements described_hook {
    /**
     * Constructor.
     *
     * @param stdClass $object The upserted object record (with id, contenthash, location, etc.).
     * @param int $newlocation The new location bitmask that was written.
     * @param int|null $oldlocation The previous location bitmask, or null for new inserts.
     */
    public function __construct(
        /** @var stdClass The upserted object record (with id, contenthash, location, etc.). */
        protected stdClass $object,
        /** @var int The new location bitmask that was written. */
        protected int $newlocation,
        /** @var int|null The previous location bitmask, or null for new inserts. */
        protected ?int $oldlocation,
    ) {
    }

    /**
     * Get the upserted object record.
     *
     * @return stdClass
     */
    public function get_object(): stdClass {
        return $this->object;
    }

    /**
     * Get the new location bitmask.
     *
     * @return int
     */
    public function get_new_location(): int {
        return $this->newlocation;
    }

    /**
     * Get the previous location bitmask.
     *
     * @return int|null Null for new inserts.
     */
    public function get_old_location(): ?int {
        return $this->oldlocation;
    }

    /**
     * Describes the hook purpose.
     *
     * @return string
     */
    public static function get_hook_description(): string {
        return 'Dispatched after an object location changes in tool_objectfs_objects. '
            . 'Listeners can maintain per-store placement records or respond to location changes.';
    }

    /**
     * List of tags that describe this hook.
     *
     * @return string[]
     */
    public static function get_hook_tags(): array {
        return ['objectfs', 'store', 'upsert'];
    }
}
