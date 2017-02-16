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

use Aws\S3\Exception\S3Exception;

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
     * @param sss_client $client S3 client
     * @param object_file_system $filesystem S3 file system
     * @param int $maxruntime What time the file manipulator should finish execution by
     */
    public function __construct($client, $maxruntime) {
         $this->client = $client;
         $this->finishtime = time() + $maxruntime;
    }

    /**
     * Returns local fullpath. We redifine this function here so
     * that our file moving functions can exist outside of the fsapi.
     * Which means filesystem_handler_class does not need to be set for them
     * to function.
     *
     * @param  string $contenthash contenthash
     * @return string fullpath to local object.
     */
    protected function get_local_fullpath_from_hash($contenthash) {
        global $CFG;
        if (isset($CFG->filedir)) {
            $filedir = $CFG->filedir;
        } else {
            $filedir = $CFG->dataroot.'/filedir';
        }
        $l1 = $contenthash[0] . $contenthash[1];
        $l2 = $contenthash[2] . $contenthash[3];
        return "$filedir/$l1/$l2/$contenthash";
    }


    /**
     * Ensures a path is readable, S3 or local. We dont want to use
     * the FS API so we redifine here.
     *
     * @param  string $contenthash contenthash
     * @return string fullpath to local object.
     */
    protected function ensure_path_is_readable($path) {
        if (!is_readable($path)) {
            throw new \file_exception('storedfilecannotread', '', $path);
        }
        return true;
    }

    protected function log_error($error, $contenthash) {
        mtrace($error->getMessage()  . "File hash: $contenthash");
    }

    /**
     * get candidate content hashes for execution.
     *
     * @return [type] [description]
     */
    abstract public function get_candidate_files();

    /**
     * execute file manipulation.
     *
     * @param  array $candidatehashes candidate content hashes
     */
    abstract public function execute($candidatehashes);
}
