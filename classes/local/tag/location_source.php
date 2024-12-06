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

/**
 * Provides location status for a file.
 *
 * @package   tool_objectfs
 * @author    Matthew Hilton <matthewhilton@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class location_source implements tag_source {
    /**
     * Identifier used in tagging file. Is the 'key' of the tag.
     * @return string
     */
    public static function get_identifier(): string {
        return 'location';
    }

    /**
     * Description for source displayed in the admin settings.
     * @return string
     */
    public static function get_description(): string {
        return get_string('tagsource:location', 'tool_objectfs');
    }

    /**
     * Returns the tag value for the given file contenthash
     * @param string $contenthash
     * @return string|null mime type for file.
     */
    public function get_value_for_contenthash(string $contenthash): ?string {
        global $DB;

        $isorphaned = $DB->record_exists('tool_objectfs_objects', ['contenthash' => $contenthash,
            'location' => OBJECT_LOCATION_ORPHANED]);

        return $isorphaned ? 'orphan' : 'active';
    }
}
