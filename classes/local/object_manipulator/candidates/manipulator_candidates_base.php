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
use stdClass;

defined('MOODLE_INTERNAL') || die();

abstract class manipulator_candidates_base implements manipulator_candidates {

    /** @var stdClass $config */
    protected $config;

    /**
     * manipulator_candidates_base constructor.
     * @param stdClass $config
     */
    public function __construct(stdClass $config) {
        $this->config = $config;
    }

    /**
     * @return array
     * @throws dml_exception
     */
    public function get() {
        global $DB;
        return $DB->get_records_sql(
            $this->get_candidates_sql(),
            $this->get_candidates_sql_params(),
            0,
            $this->config->batchsize
        );
    }
}
