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
 * Hook dispatched after a file is successfully pushed to the primary external store.
 *
 * Listeners can use this to push the same file to additional stores and record
 * per-store placement information.
 *
 * @package   tool_objectfs
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class after_push_to_external implements described_hook {
    /**
     * Constructor.
     *
     * @param string $contenthash The content hash of the file being pushed.
     * @param int $filesize The size of the file in bytes.
     * @param string $localpath The full local filesystem path to the file.
     */
    public function __construct(
        /** @var string The content hash of the file being pushed. */
        protected string $contenthash,
        /** @var int The size of the file in bytes. */
        protected int $filesize,
        /** @var string The full local filesystem path to the file. */
        protected string $localpath,
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
     * Get the file size.
     *
     * @return int
     */
    public function get_filesize(): int {
        return $this->filesize;
    }

    /**
     * Get the local file path.
     *
     * @return string
     */
    public function get_localpath(): string {
        return $this->localpath;
    }

    /**
     * Describes the hook purpose.
     *
     * @return string
     */
    public static function get_hook_description(): string {
        return 'Dispatched after a file is successfully pushed to the primary external store. '
            . 'Listeners can push to additional stores and record per-store placements.';
    }

    /**
     * List of tags that describe this hook.
     *
     * @return string[]
     */
    public static function get_hook_tags(): array {
        return ['objectfs', 'store', 'push'];
    }
}
