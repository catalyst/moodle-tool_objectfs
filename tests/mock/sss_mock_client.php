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
 * local_catdeleter scheduler tests.
 *
 * @package   local_catdeleter
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_sssfs\sss_client;
use core_files\filestorage\file_exception;
use Aws\S3\Exception\S3Exception;

defined('MOODLE_INTERNAL') || die;


class sss_mock_client extends sss_client {

    private $return;
    private $throwexception;

    public function __construct() {
        global $CFG;
        $this->return = true;
        $this->throwexception = false;
        mkdir($CFG->phpunit_dataroot . '/mockbucket');
    }

    public function set_return($return) {
        $this->return = $return;
    }

    public function set_throw_exception($throwexception) {
        $this->throwexception = $throwexception;
    }

    public function push_file($filekey, $filecontent) {
        if ($this->throwexception) {
            throw new S3Exception('Mock S3 exception', 'file', 'line');
        } else {
            $mockpath = $this->get_fullpath_from_hash($filekey);
            file_put_contents($mockpath, $filecontent);
            return true;
        }
    }

    public function check_file($filekey, $expectedsize) {
        if ($this->throwexception) {
            throw new file_exception('storedfilecannotread', '', $this->get_fullpath_from_hash($filekey));
        } else if ($this->return) {
            return true; // Mock a failure.
        } else {
            return false;
        }
    }

    // Returns s3 fullpath to use with php file functions.
    public function get_fullpath_from_hash($contenthash) {
        global $CFG;
        return "{$CFG->phpunit_dataroot}/mockbucket/{$contenthash}";
    }
}

