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

class test_client extends object_client {

    private $bucketpath;

    public function __construct($config) {
        global $CFG;
        $dataroot = $CFG->phpunit_dataroot;
        if (defined('PHPUNIT_INSTANCE') && PHPUNIT_INSTANCE !== null) {
            $dataroot .= '/' . PHPUNIT_INSTANCE;
        }
        $this->bucketpath = $dataroot . '/mockbucket';
        if (!is_dir($this->bucketpath)) {
            mkdir($this->bucketpath);
        }
        // Trashdir for local storage.
        if (!is_dir($this->bucketpath . '/trashdir')) {
            mkdir($this->bucketpath . '/trashdir');
        }
        // Trashdir for external storage.
        if (!is_dir($this->bucketpath . '/trash')) {
            mkdir($this->bucketpath . '/trash');
        }
    }

    public function get_seekable_stream_context() {
        $context = stream_context_create();
        return $context;
    }

    public function get_fullpath_from_hash($contenthash) {
        return "$this->bucketpath/{$contenthash}";
    }

    public function get_trash_fullpath_from_hash($contenthash) {
        return "$this->bucketpath/trashdir/{$contenthash}";
    }

    public function delete_file($fullpath) {
        return unlink($fullpath);
    }

    public function rename_file($currentpath, $destinationpath) {
        return rename($currentpath, $destinationpath);
    }

    public function get_external_trash_path_from_hash($contenthash) {
        return "$this->bucketpath/trash/{$contenthash}";
    }

    public function register_stream_wrapper() {
        return true;
    }

    private function get_md5_from_hash($contenthash) {
        $path = $this->get_fullpath_from_hash($contenthash);
        return md5_file($path);
    }

    public function verify_object($contenthash, $localpath) {
        // For objects uploaded to S3 storage using the multipart upload, the etag will not be the objects MD5.
        // So we can't compare here to verify the object.
        // For now we just check that we can retrieve any Etag to verify the object for all supported storages.
        $retrievemd5 = $this->get_md5_from_hash($contenthash);
        if ($retrievemd5) {
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

