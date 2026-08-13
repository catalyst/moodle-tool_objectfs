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
 * Hook dispatched after manipulator candidates are fetched, before processing.
 *
 * Listeners can filter the candidate list (e.g. exclude files already placed in all
 * write-enabled stores) or inject additional candidates for processing.
 *
 * @package   tool_objectfs
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_candidates implements described_hook {
    /**
     * Constructor.
     *
     * @param string $manipulatorclass The fully-qualified manipulator class name.
     * @param array $candidates The candidate records (mutable).
     */
    public function __construct(
        /** @var string The fully-qualified manipulator class name. */
        protected string $manipulatorclass,
        /** @var array The candidate records (mutable). */
        protected array $candidates,
    ) {
    }

    /**
     * Get the manipulator class name.
     *
     * @return string
     */
    public function get_manipulator_class(): string {
        return $this->manipulatorclass;
    }

    /**
     * Get the current candidate list.
     *
     * @return array
     */
    public function get_candidates(): array {
        return $this->candidates;
    }

    /**
     * Replace the candidate list.
     *
     * @param array $candidates The filtered/modified candidate records.
     */
    public function set_candidates(array $candidates): void {
        $this->candidates = $candidates;
    }

    /**
     * Describes the hook purpose.
     *
     * @return string
     */
    public static function get_hook_description(): string {
        return 'Dispatched after manipulator candidates are fetched. Listeners can filter out files '
            . 'already in all write-enabled stores or inject additional candidates.';
    }

    /**
     * List of tags that describe this hook.
     *
     * @return string[]
     */
    public static function get_hook_tags(): array {
        return ['objectfs', 'store', 'candidates'];
    }
}
