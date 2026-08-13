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
 * Hook dispatched after a file is successfully pulled from an external store to local filedir.
 *
 * Listeners can use this to track which store served the file or update placement records.
 *
 * @package   tool_objectfs
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class after_pull_from_external implements described_hook {
    /**
     * Constructor.
     *
     * @param string $contenthash The content hash of the file that was pulled.
     * @param int $filesize The size of the file in bytes.
     */
    public function __construct(
        /** @var string The content hash of the file that was pulled. */
        protected string $contenthash,
        /** @var int The size of the file in bytes. */
        protected int $filesize,
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
     * Describes the hook purpose.
     *
     * @return string
     */
    public static function get_hook_description(): string {
        return 'Dispatched after a file is successfully pulled from external storage to local filedir. '
            . 'Listeners can track which store served the file.';
    }

    /**
     * List of tags that describe this hook.
     *
     * @return string[]
     */
    public static function get_hook_tags(): array {
        return ['objectfs', 'store', 'pull'];
    }
}
