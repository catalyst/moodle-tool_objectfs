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

/**
 * [Description real_time_logger]
 */
class real_time_logger extends objectfs_logger {

    /**
     * log_object_read_action
     * @param string $actionname
     * @param string $objectpath
     *
     * @return mixed
     */
    public function log_object_read_action($actionname, $objectpath) {

    }

    /**
     * log_object_move_action
     * @param string $actionname
     * @param string $objecthash
     * @param string $initallocation
     * @param string $finallocation
     *
     * @return mixed
     */
    public function log_object_move_action($actionname, $objecthash, $initallocation, $finallocation) {

    }

    /**
     * append_timing_string
     * @param mixed $logstring
     *
     * @return void
     */
    protected function append_timing_string(&$logstring) {
        $timetaken = $this->get_timing();
        if ($timetaken > 0) {
            $logstring .= "Time taken was: $timetaken seconds. ";
        }

    }

    /**
     * append_size_string
     * @param string $logstring
     * @param int $objectsize
     *
     * @return void
     */
    protected function append_size_string(&$logstring, $objectsize) {
        if ($objectsize > 0) {
            $objectsize = display_size($objectsize);
            $logstring .= "The object's size was $objectsize";
        }
    }

    /**
     * append_location_change_string
     * @param string $logstring
     * @param string $initiallocation
     * @param string $finallocation
     *
     * @return void
     */
    protected function append_location_change_string(&$logstring, $initiallocation, $finallocation) {
        if ($initiallocation == $finallocation) {
            $logstring .= "The object location did not change from $initiallocation. ";
        } else {
            $logstring .= "The object location changed from $initiallocation to $finallocation. ";
        }
    }

    /**
     * log_object_read
     * @param string $readname
     * @param string $objectpath
     * @param int $objectsize
     *
     * @return [type]
     */
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

    /**
     * log_object_move
     * @param string $movename
     * @param string $initallocation
     * @param string $finallocation
     * @param string $objecthash
     * @param int $objectsize
     *
     * @return void
     */
    public function log_object_move($movename, $initallocation, $finallocation, $objecthash, $objectsize = 0) {
        $logstring = "The move action '$movename' was performed on object with hash $objecthash. ";
        $this->append_location_change_string($logstring, $initallocation, $finallocation);
        $this->append_timing_string($logstring);
        $this->append_size_string($logstring, $objectsize);
        // @codingStandardsIgnoreStart
        error_log($logstring);
        // @codingStandardsIgnoreEnd
    }

    /**
     * log_object_query
     * @param string $queryname
     * @param int $objectcount
     * @param int $objectsum
     *
     * @return void
     */
    public function log_object_query($queryname, $objectcount, $objectsum = 0) {
        $logstring = "The query action '$queryname' was performed. $objectcount objects were returned";
        $this->append_timing_string($logstring);
        // @codingStandardsIgnoreStart
        error_log($logstring);
        // @codingStandardsIgnoreEnd
    }

    /**
     * log_lock_timing
     * @param mixed $lock
     *
     * @return void
     */
    public function log_lock_timing($lock) {
        $locktime = $this->get_timing();
        if ($lock) {
            $this->error_log('Lock acquired in '.$locktime.' seconds.');
        } else {
            $this->error_log('Can\'t acquire lock. Time waited '.$locktime.' seconds.');
        }
    }
}
