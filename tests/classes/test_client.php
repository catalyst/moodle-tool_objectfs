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

    public function __construct($config) {
        global $CFG;
        $this->bucketpath = $CFG->phpunit_dataroot . '/mockbucket';
        if (!is_dir($this->bucketpath)) {
            mkdir($this->bucketpath);
        }
    }

    public function get_seekable_stream_context() {
        $context = stream_context_create();
        return $context;
    }

    public function get_fullpath_from_hash($contenthash) {
        return "$this->bucketpath/{$contenthash}";
    }

    public function register_stream_wrapper() {
        return true;
    }

    private function get_md5_from_hash($contenthash) {
        $path = $this->get_fullpath_from_hash($contenthash);
        return md5_file($path);
    }

    public function verify_object($contenthash, $localpath) {
        $localmd5 = md5_file($localpath);
        $externalmd5 = $this->get_md5_from_hash($contenthash);
        if ($localmd5 === $externalmd5) {
            return true;
        }
        return false;
    }

    public function test_connection() {
        return true;
    }

    public function test_permissions() {
        return true;
    }

    public function get_availability() {
        return true;
    }

    public function get_maximum_upload_size() {
        return 5000000000;
    }
}

