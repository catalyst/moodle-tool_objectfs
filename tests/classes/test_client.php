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

namespace tool_objectfs\tests;

defined('MOODLE_INTERNAL') || die();

use tool_objectfs\object_file_system;
use tool_objectfs\client\object_client;

class test_client implements object_client {

    private $bucketpath;
    private $verifyfails;

    public function __construct($config) {
        global $CFG;
        $this->bucketpath = $CFG->phpunit_dataroot . '/mockbucket';
        if (!is_dir($this->bucketpath)) {
            mkdir($this->bucketpath);
        }
        $this->verifyfails = false;
    }
    public function register_stream_wrapper() {
        // Do nothing - we are using standard file paths.
    }

    public function get_remote_md5_from_hash($contenthash) {
        $path = $this->get_remote_fullpath_from_hash($contenthash);
        return md5_file($path);
    }

    public function verify_remote_object($contenthash, $localpath) {
        $localmd5 = md5_file($localpath);
        $remotemd5 = $this->get_remote_md5_from_hash($contenthash);
        if ($localmd5 === $remotemd5) {
            return true;
        }
        return false;
    }

    public function get_remote_fullpath_from_hash($contenthash) {
        return "$this->bucketpath/{$contenthash}";
    }
    public function test_connection() {
        return true;
    }
    public function permissions_check() {
        return true;
    }

}

