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
 * Tag source interface
 *
 * @package   tool_objectfs
 * @author    Matthew Hilton <matthewhilton@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface tag_source {
    /**
     * Returns an unchanging identifier for this source.
     * Must never change, otherwise it will lose connection with the tags replicated to objects.
     * If it ever must change, a migration step must be completed to trigger all objects to recalculate their tags.
     * Must not exceed 128 chars.
     * @return string
     */
    public static function get_identifier(): string;

    /**
     * Description for source displayed in the admin settings.
     * @return string
     */
    public static function get_description(): string;

    /**
     * Returns the value of this tag for the file with the given content hash.
     * This must be deterministic, and should never exceed 128 chars.
     * @param string $contenthash
     * @return string
     */
    public function get_value_for_contenthash(string $contenthash): ?string;
}
