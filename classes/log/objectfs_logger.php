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
 * objectfs logger abstract class.
 *
 * @package   tool_objectfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\log;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/admin/tool/objectfs/lib.php');

abstract class objectfs_logger {
    protected $timestart;
    protected $timeend;

    public function __construct() {
        $this->timestart = 0;
        $this->timeend = 0;
    }

    public function start_timing() {
        $this->timestart = microtime(true);
        return $this->timestart;
    }

    public function end_timing() {
        $this->timeend = microtime(true);
        return $this->timeend;
    }

    protected function get_timing() {
        return $this->timeend - $this->timestart;
    }

    public function error_log($error) {
        // @codingStandardsIgnoreStart
        error_log($error);
        // @codingStandardsIgnoreEnd
    }

    public abstract function log_object_read($readname, $objectpath, $objectsize = 0);
    public abstract function log_object_move($movename, $initallocation, $finallocation, $objecthash, $objectsize = 0);
    public abstract function log_object_query($queryname, $objectcount, $objectsum = 0);
}
