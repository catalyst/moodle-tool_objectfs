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
 * File manipulator abstract class.
 *
 * @package   tool_objectfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\object_manipulator;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/admin/tool/objectfs/lib.php');

abstract class manipulator {

    /**
     * object file system
     *
     * @var object_file_system
     */
    protected $filesystem;

    /**
     * What time the file manipulator should finish execution by.
     *
     * @var int
     */
    protected $finishtime;

    /**
     * Manipulator constructor
     *
     * @param object_file_system $filesystem object file system
     * @param int $maxruntime What time the file manipulator should finish execution by
     */
    public function __construct($filesystem, $config) {
         $this->finishtime = time() + $config['maxtaskruntime'];
         $this->filesystem = $filesystem;
    }

    /**
     * get candidate content hashes for execution.
     *
     * @return array $candidatehashes candidate content hashes
     */
    abstract public function get_candidate_objects();

    /**
     * execute file manipulation.
     *
     * @param  array $candidatehashes candidate content hashes
     */
    abstract public function execute($candidatehashes);
}
