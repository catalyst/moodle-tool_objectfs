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

namespace tool_objectfs\local\tag;

use moodle_exception;

/**
 * Provides current environment to file.
 *
 * @package   tool_objectfs
 * @author    Matthew Hilton <matthewhilton@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class environment_source implements tag_source {
    /**
     * Identifier used in tagging file. Is the 'key' of the tag.
     * @return string
     */
    public static function get_identifier(): string {
        return 'environment';
    }

    /**
     * Description for source displayed in the admin settings.
     * @return string
     */
    public static function get_description(): string {
        return get_string('tagsource:environment', 'tool_objectfs', self::get_env());
    }

    /**
     * Returns current env value from $CFG
     * @return string|null string if set, else null
     */
    private static function get_env(): ?string {
        global $CFG;

        if (empty($CFG->objectfs_environment_name)) {
            return null;
        }

        // Must never be greater than 128, unlikely, but we must enforce this.
        if (strlen($CFG->objectfs_environment_name) > 128) {
            throw new moodle_exception('tagsource:environment:toolong', 'tool_objectfs');
        }

        return $CFG->objectfs_environment_name;
    }

    /**
     * Returns the tag value for the given file contenthash
     * @param string $contenthash
     * @return string|null environment value.
     */
    public function get_value_for_contenthash(string $contenthash): ?string {
        return self::get_env();
    }
}
