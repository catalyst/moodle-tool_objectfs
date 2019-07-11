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
 * objectfs null logger class.
 *
 * @package   tool_objectfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\log;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/admin/tool/objectfs/lib.php');

class real_time_logger extends objectfs_logger {

    public function log_object_read_action($actionname, $objectpath) {

    }

    public function log_object_move_action($actionname, $objecthash, $initallocation, $finallocation) {

    }

    protected function append_timing_string(&$logstring) {
        $timetaken = $this->get_timing();
        if ($timetaken > 0) {
            $logstring .= "Time taken was: $timetaken seconds. ";
        }

    }

    protected function append_size_string(&$logstring, $objectsize) {
        if ($objectsize > 0) {
            $objectsize = display_size($objectsize);
            $logstring .= "The object's size was $objectsize";
        }
    }

    protected function append_location_change_string(&$logstring, $initiallocation, $finallocation) {
        if ($initiallocation == $finallocation) {
            $logstring .= "The object location did not change from $initiallocation. ";
        } else {
            $logstring .= "The object location changed from $initiallocation to $finallocation. ";
        }
    }

    public function log_object_read($readname, $objectpath, $objectsize = 0) {
        $logstring = "The read action '$readname' was used on object with path $objectpath. ";
        $this->append_timing_string($logstring);
        if ($objectsize > 0) {
            $this->append_size_string($logstring, $objectsize);
        }
        // @codingStandardsIgnoreStart
        error_log($logstring);
        // @codingStandardsIgnoreEnd
    }

    public function log_object_move($movename, $initallocation, $finallocation, $objecthash, $objectsize = 0) {
        $logstring = "The move action '$movename' was performed on object with hash $objecthash. ";
        $this->append_location_change_string($logstring, $initallocation, $finallocation);
        $this->append_timing_string($logstring);
        $this->append_size_string($logstring, $objectsize);
        // @codingStandardsIgnoreStart
        error_log($logstring);
        // @codingStandardsIgnoreEnd
    }

    public function log_object_query($queryname, $objectcount, $objectsum = 0) {
        $logstring = "The query action '$queryname' was performed. $objectcount objects were returned";
        $this->append_timing_string($logstring);
        // @codingStandardsIgnoreStart
        error_log($logstring);
        // @codingStandardsIgnoreEnd
    }
}
