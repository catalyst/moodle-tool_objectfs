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
 * Orphans {tool_objectfs_objects} records for files that have been
 * deleted from the core {files} table.
 *
 * @package   tool_objectfs
 * @author    Nathan Mares <ngmares@gmail.com>
 * @author    Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\local\object_manipulator;

use stdClass;

defined('MOODLE_INTERNAL') || die();

class orphaner extends manipulator {

    /**
     * Updates the location of {tool_objectfs_objects} records for files that
     * have been deleted from the core {files} table.
     *
     * @param \stdClass $objectrecord
     * @return int
     */
    public function manipulate_object(stdClass $objectrecord): int {
        return OBJECT_LOCATION_ORPHANED;
    }
}
