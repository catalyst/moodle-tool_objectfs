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
 * Hook dispatched when the primary external store cannot provide a readable path for a file.
 *
 * Listeners can provide an alternative path from another store (read fallback).
 * If a listener sets a resolved path, the base plugin will use it instead of
 * returning the primary store's (unreadable) path.
 *
 * @package   tool_objectfs
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class resolve_external_path implements described_hook {
    /** @var string|null The resolved path set by a listener, or null if unresolved. */
    private ?string $resolvedpath = null;

    /**
     * Constructor.
     *
     * @param string $contenthash The content hash of the file being resolved.
     */
    public function __construct(
        /** @var string The content hash of the file being resolved. */
        protected string $contenthash,
    ) {
    }

    /**
     * Get the content hash.
     *
     * @return string
     */
    public function get_contenthash(): string {
        return $this->contenthash;
    }

    /**
     * Set the resolved path from an alternative store.
     *
     * @param string $path The full path to the file in an alternative store.
     */
    public function set_resolved_path(string $path): void {
        $this->resolvedpath = $path;
    }

    /**
     * Get the resolved path, if any listener provided one.
     *
     * @return string|null
     */
    public function get_resolved_path(): ?string {
        return $this->resolvedpath;
    }

    /**
     * Check if a listener has resolved the path.
     *
     * @return bool
     */
    public function is_resolved(): bool {
        return $this->resolvedpath !== null;
    }

    /**
     * Describes the hook purpose.
     *
     * @return string
     */
    public static function get_hook_description(): string {
        return 'Dispatched when the primary external store cannot provide a readable path. '
            . 'Listeners can provide an alternative path from another store for read fallback.';
    }

    /**
     * List of tags that describe this hook.
     *
     * @return string[]
     */
    public static function get_hook_tags(): array {
        return ['objectfs', 'store', 'read', 'fallback'];
    }
}
