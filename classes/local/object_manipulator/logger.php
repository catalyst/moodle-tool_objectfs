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

/** Logs manipulator actions
 *
 * @package   tool_objectfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\local\object_manipulator;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/admin/tool/objectfs/lib.php');

/**
 * logger
 */
class logger {

    /**
     * @var [type]
     */
    private $action; // Which action to log.
    /**
     * @var int
     */
    private $timestart;
    /**
     * @var int
     */
    private $timeend;
    /**
     * @var int
     */
    private $totalfilesize;
    /**
     * @var int
     */
    private $totalfilecount;

    /**
     * construct
     */
    public function __construct() {
        $this->totalfilecount = 0;
        $this->totalfilesize = 0;
    }

    /**
     * start_timing
     * @return void
     */
    public function start_timing() {
        $this->timestart = time();
    }

    /**
     * end_timing
     * @return void
     */
    public function end_timing() {
        $this->timeend = time();
    }

    /**
     * set_action
     * @param mixed $action
     * 
     * @return void
     */
    public function set_action($action) {
        $this->action = $action;
    }

    /**
     * add_object_manipulation
     * @param int $filesize
     * 
     * @return void
     */
    public function add_object_manipulation($filesize) {
        $this->totalfilesize += $filesize;
        $this->totalfilecount++;
    }

    /**
     * log_object_manipulation
     * @return void
     */
    public function log_object_manipulation() {
        $duration = $this->timestart - $this->timeend;
        $totalfilesize = display_size($this->totalfilesize);
        $logstring = "Objectsfs $this->action manipulator took $duration seconds ";
        $logstring .= "to $this->action $this->totalfilecount objects. ";
        $logstring .= "Total size: $totalfilesize Total time: $duration seconds";
        mtrace($logstring);
    }

    /**
     * log_object_manipulation_query
     * @param mixed $totalobjectsfound
     * 
     * @return void
     */
    public function log_object_manipulation_query($totalobjectsfound) {
        $duration = $this->timeend - $this->timestart;
        $logstring = "Objectsfs $this->action manipulator took $duration seconds ";
        $logstring .= "to find $totalobjectsfound potential $this->action objects. ";
        $logstring .= "Total time: $duration seconds";
        mtrace($logstring);
    }
}
