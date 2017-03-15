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

/* Logs manipulator actions
 *
 * @package   tool_objectfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\object_manipulator;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/admin/tool/objectfs/lib.php');

class logger {

    private $action; // Which action to log.
    private $timestart;
    private $timeend;
    private $totalfilesize;
    private $totalfilecount;

    public function __construct() {
        $this->totalfilecount = 0;
        $this->totalfilesize = 0;
    }

    public function start_timing() {
        $this->timestart = time();
    }

    public function end_timing() {
        $this->timeend = time();
    }

    public function set_action($action) {
        $this->action = $action;
    }

    public function add_object_manipulation($filesize) {
        $this->totalfilesize += $filesize;
        $this->totalfilecount++;
    }

    public function log_object_manipulation() {
        $duration = $this->timestart - $this->timeend;
        $totalfilesize = display_size($this->totalfilesize);
        $logstring = "Objectsfs $this->action manipulator took $duration seconds to $this->action $this->totalfilecount objects. ";
        $logstring .= "Total size: $totalfilesize Total time: $duration seconds";
        mtrace($logstring);
    }

    public function log_object_manipulation_query($totalobjectsfound) {
        $duration = $this->timeend - $this->timestart;
        $logstring = "Objectsfs $this->action manipulator took $duration seconds to find $totalobjectsfound potential $this->action objects. ";
        $logstring .= "Total time: $duration seconds";
        mtrace($logstring);
    }
}
