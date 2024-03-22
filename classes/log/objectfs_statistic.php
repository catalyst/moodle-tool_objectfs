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
 * objectfs statistic container class.
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
 * [Description objectfs_statistic]
 */
class objectfs_statistic {

    /**
     * @var string
     */
    private $key;

    /**
     * @var int
     */
    private $objectcount;

    /**
     * @var int
     */
    private $objectsum;

    /**
     * construct
     * @param string $key
     */
    public function __construct($key) {
        $this->key = $key;
        $this->objectcount = 0;
        $this->objectsum = 0;
    }

    /**
     * get_objectcount
     * @return int
     */
    public function get_objectcount() {
        return $this->objectcount;
    }

    /**
     * get_objectsum
     * @return int
     */
    public function get_objectsum() {
        return $this->objectsum;
    }

    /**
     * get_key
     * @return string
     */
    public function get_key() {
        return $this->key;
    }

    /**
     * add_statistic
     * @param objectfs_statistic $statistic
     * 
     * @return void
     */
    public function add_statistic(objectfs_statistic $statistic) {
        $this->objectcount += $statistic->get_objectcount();
        $this->objectsum += $statistic->get_objectsum();
    }

    /**
     * add_object_data
     * @param int $objectcount
     * @param int $objectsum
     * 
     * @return void
     */
    public function add_object_data($objectcount, $objectsum) {
        $this->objectcount += $objectcount;
        $this->objectsum += $objectsum;
    }
}
