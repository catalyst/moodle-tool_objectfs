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

use tool_objectfs\log\objectfs_statistic;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/admin/tool/objectfs/lib.php');

/**
 * [Description aggregate_logger]
 */
class aggregate_logger extends objectfs_logger {

    /**
     * 1d array of objectfs_statistics.
     * @var array
     */
    private $readstatistics;

    /**
     * 2d array of objecfs_statistics that is lazily setup.
     * @var array
     */
    private $movestatistics;

    /**
     * @var mixed
     */
    private $movement;

    /**
     * @var array
     */
    private $querystatistics;

    /**
     * construct
     * @return void
     */
    public function __construct() {
        parent::__construct();
        $this->movestatistics = [
            OBJECT_LOCATION_ERROR => [],
            OBJECT_LOCATION_LOCAL => [],
            OBJECT_LOCATION_DUPLICATED => [],
            OBJECT_LOCATION_EXTERNAL => [],
        ];
        $this->readstatistics = [];
        $this->querystatistics = [];
    }

    /**
     * log_object_read
     * @param string $readname
     * @param string $objectpath
     * @param int $objectsize
     *
     * @return void
     */
    public function log_object_read($readname, $objectpath, $objectsize = 0) {
        if (array_key_exists($readname, $this->readstatistics)) {
            $readstat = $this->readstatistics[$readname];
        } else {
            $readstat = new objectfs_statistic($readname);
        }

        $readstat->add_object_data(1, $objectsize);
        $this->readstatistics[$readname] = $readstat;
    }

    /**
     * log_object_move
     * @param mixed $movename
     * @param mixed $initallocation
     * @param mixed $finallocation
     * @param mixed $objecthash
     * @param int $objectsize
     *
     * @return void
     */
    public function log_object_move($movename, $initallocation, $finallocation, $objecthash, $objectsize = 0) {
        if (!$this->movement) {
            $this->movement = $movename;
        }

        if (array_key_exists($finallocation, $this->movestatistics[$initallocation])) {
            $movestat = $this->movestatistics[$initallocation][$finallocation];
        } else {
            $movestat = new objectfs_statistic($movename);
        }

        $movestat->add_object_data(1, $objectsize);
        $this->movestatistics[$initallocation][$finallocation] = $movestat;
    }

    /**
     * output_move_statistics
     * @return void
     */
    public function output_move_statistics() {
        $totaltime = $this->get_timing();
        mtrace("$this->movement. Total time taken: $totaltime seconds. Location change summary:");
        foreach ($this->movestatistics as $iniloc => $finlocarr) {
            foreach ($finlocarr as $finloc => $movestat) {
                $this->output_move_statistic($movestat, $iniloc, $finloc);
            }
        }
    }

    /**
     * output_move_statistic
     * @param mixed $movestatistic
     * @param string $initiallocation
     * @param string $finallocation
     *
     * @return void
     */
    protected function output_move_statistic($movestatistic, $initiallocation, $finallocation) {
        $key = $movestatistic->get_key();
        $objectcount = $movestatistic->get_objectcount();
        $objectsum = $movestatistic->get_objectsum();
        $objectsum = display_size($objectsum);
        $initiallocation = $this->location_to_string($initiallocation);
        $finallocation = $this->location_to_string($finallocation);
        mtrace("$initiallocation -> $finallocation. Objects moved: $objectcount. Total size: $objectsum. ");
    }

    /**
     * location_to_string
     * @param int $location
     *
     * @return string
     */
    public function location_to_string($location) {
        switch ($location) {
            case OBJECT_LOCATION_ERROR:
                return 'error';
            case OBJECT_LOCATION_LOCAL:
                return 'local';
            case OBJECT_LOCATION_DUPLICATED:
                return 'duplicated';
            case OBJECT_LOCATION_EXTERNAL:
                return 'remote';
            case OBJECT_LOCATION_ORPHANED:
                return 'orphaned';
            default:
                return $location;
        }
    }

    /**
     * log_object_query
     * @param mixed $queryname
     * @param int $objectcount
     * @param int $objectsum
     *
     * @return [type]
     */
    public function log_object_query($queryname, $objectcount, $objectsum = 0) {
        if (array_key_exists($queryname, $this->querystatistics)) {
            $querystat = $this->querystatistics[$queryname];
        } else {
            $querystat = new objectfs_statistic($queryname);
        }

        $querystat->add_object_data($objectcount, $objectsum);
        $this->querystatistics[$queryname] = $querystat;
    }
}
