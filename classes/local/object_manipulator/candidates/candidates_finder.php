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

use dml_exception;
use moodle_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();

class candidates_finder {

    /** @var candidates_factory $factory */
    private $factory;

    /** @var string $finder */
    private $finder = '';

    /**
     * candidates_finder constructor.
     * @param string $manipulator
     * @param stdClass $config
     * @throws moodle_exception
     */
    public function __construct($manipulator, stdClass $config) {
        $this->factory = new candidates_factory($manipulator, $config);
    }

    /**
     * @return array
     * @throws moodle_exception
     * @throws dml_exception
     */
    public function get() {
        $this->finder = $this->factory->finder();
        return $this->finder->get();
    }

    /**
     * @inheritDoc
     * @return string
     */
    public function get_query_name() {
        return $this->finder->get_query_name();
    }
}
