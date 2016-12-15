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
 * @package   tool_sssfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_sssfs\file_manipulators;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/admin/tool/sssfs/lib.php');

abstract class manipulator {

    /**
     * S3 client
     *
     * @var sss_client
     */
    protected $client;

    /**
     * S3 file system
     *
     * @var sss_file_system
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
     * @param sss_client $client S3 client
     * @param sss_file_system $filesystem S3 file system
     * @param int $maxruntime What time the file manipulator should finish execution by
     */
    public function __construct($client, $filesystem, $maxruntime) {
         $this->client = $client;
         $this->filesystem = $filesystem;
         $this->finishtime = time() + $maxruntime;
    }

    /**
     * get candidate content hashes for execution.
     *
     * @return [type] [description]
     */
    abstract public function get_candidate_content_hashes();

    /**
     * execute file manipulation.
     *
     * @param  array $candidatehashes candidate content hashes
     */
    abstract public function execute($candidatehashes);
}
