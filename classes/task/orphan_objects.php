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
 * Task that orphans {tool_objectfs_object} records for deleted {files} records.
 *
 * @package   tool_objectfs
 * @author    Nathan Mares <ngmares@gmail.com>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\task;

use tool_objectfs\local\object_manipulator\orphaner;


/**
 * [Description orphan_objects]
 */
class orphan_objects extends task {

    /** @var string $manipulator */
    protected $manipulator = orphaner::class;

    /** @var string $stringname */
    protected $stringname = 'orphan_objects_task';
}
