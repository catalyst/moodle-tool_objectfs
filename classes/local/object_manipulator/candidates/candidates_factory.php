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
 * Class candidates_factory
 * @package tool_objectfs
 * @author Gleimer Mora <gleimermora@catalyst-au.net>
 * @copyright Catalyst IT
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\local\object_manipulator\candidates;

use moodle_exception;
use stdClass;
use tool_objectfs\local\object_manipulator\checker;
use tool_objectfs\local\object_manipulator\deleter;
use tool_objectfs\local\object_manipulator\puller;
use tool_objectfs\local\object_manipulator\pusher;
use tool_objectfs\local\object_manipulator\recoverer;
use tool_objectfs\local\object_manipulator\orphaner;

class candidates_factory {

    /** @var array $manipulatormap */
    private static $manipulatormap = [
        checker::class => checker_candidates::class,
        deleter::class => deleter_candidates::class,
        puller::class => puller_candidates::class,
        pusher::class => pusher_candidates::class,
        recoverer::class => recoverer_candidates::class,
        orphaner::class => orphaner_candidates::class,
    ];

    /**
     * @param $manipulator
     * @param stdClass $config
     * @return mixed
     * @throws moodle_exception
     */
    public static function finder($manipulator, stdClass $config) {
        if (isset(self::$manipulatormap[$manipulator])) {
            $classname = self::$manipulatormap[$manipulator];
            return new $classname($config);
        }
        throw new moodle_exception('invalidclass', 'error', '', 'Invalid manipulator class');
    }
}
