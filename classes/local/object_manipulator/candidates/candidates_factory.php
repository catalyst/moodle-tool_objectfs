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

defined('MOODLE_INTERNAL') || die();

class candidates_factory {

    /** @var manipulator_candidates_base $finder  */
    private $finder;

    /** @var array $manipulatormap */
    private $manipulatormap = [
        checker::class => checker_candidates::class,
        deleter::class => deleter_candidates::class,
        puller::class => puller_candidates::class,
        pusher::class => pusher_candidates::class,
        recoverer::class => recoverer_candidates::class,
    ];

    /**
     * candidates_factory constructor.
     * @param string $manipulator
     * @param stdClass $config
     * @throws moodle_exception
     */
    public function __construct($manipulator, stdClass $config) {
        if (isset($this->manipulatormap[$manipulator])) {
            $classname = $this->manipulatormap[$manipulator];
            $this->finder = new $classname($config);
        } else {
            throw new moodle_exception('invalidclass', 'error', '', 'Invalid manipulator class');
        }
    }

    /**
     * @return manipulator_candidates_base
     */
    public function finder() {
        return $this->finder;
    }
}
