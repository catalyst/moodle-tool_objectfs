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

namespace tool_objectfs\tests;

use tool_objectfs\config\config as config_base;

defined('MOODLE_INTERNAL') || die();

class config extends config_base {

    /**
     * @param array $config
     */
    static public function set_config(array $config) {
        foreach ($config as $key => $value) {
            set_config($key, $value, 'tool_objectfs');
            call_user_func_array([self::class, 'reset_config'], [$key, $value]);
        }
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    static private function reset_config($key, $value) {
        if (isset(self::$instances[self::class])) {
            self::$instances[self::class]->config->$key = $value;
        }
    }
}
