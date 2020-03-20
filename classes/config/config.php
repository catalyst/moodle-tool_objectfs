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
 * Objectfs config class.
 *
 * @package   tool_objectfs
 * @author    Gleimer Mora <gleimermora@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\config;

use dml_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die;

final class config extends singleton {

    /** @var stdClass $config */
    private $config;

    /**
     * config constructor.
     * @throws dml_exception
     */
    protected function __construct() {
        parent::__construct();
        $this->config = get_config('tool_objectfs');
    }

    /**
     * @param $name
     * @return bool
     */
    public function get($name) {
        if (!empty($this->config->$name)) {
            return $this->config->$name;
        }
        return false;
    }
}
