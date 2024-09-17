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

use tool_objectfs\local\store\object_client_base;

/**
 * Test client for PHP unit tests
 *
 * @package   tool_objectfs
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class test_client extends object_client_base {
    /**
     * Maximum allowed file size that can be uploaded
     * @var int
     */
    protected $maxupload;

    /**
     * @var string
     */
    private $bucketpath;

    /**
     * string
     * @param \stdClass $config
     */
    public function __construct($config) {
        global $CFG;
        $this->maxupload = 5000000000;
        $this->config = $config;
        // Point autoloader to this file to make sure get_client_availability() returns true.
        $this->autoloader = $CFG->dirroot . '/admin/tool/objectfs/classes/tests/test_client.php';
        $dataroot = $CFG->phpunit_dataroot;
        if (defined('PHPUNIT_INSTANCE') && PHPUNIT_INSTANCE !== null) {
            $dataroot .= '/' . PHPUNIT_INSTANCE;
        }
        $this->bucketpath = $dataroot . '/mockbucket';
        if (!is_dir($this->bucketpath)) {
            mkdir($this->bucketpath);
        }
    }

    /**
     * get_seekable_stream_context
     * @return resource
     */
    public function get_seekable_stream_context() {
        $context = stream_context_create();
        return $context;
    }

    /**
     * get_fullpath_from_hash
     * @param string $contenthash
     *
     * @return string
     */
    public function get_fullpath_from_hash($contenthash) {
        return "$this->bucketpath/{$contenthash}";
    }


    /**
     * delete_file
     * @param string $fullpath
     *
     * @return bool
     */
    public function delete_file($fullpath) {
        return unlink($fullpath);
    }

    /**
     * rename_file
     * @param string $currentpath
     * @param string $destinationpath
     *
     * @return bool
     */
    public function rename_file($currentpath, $destinationpath) {
        return rename($currentpath, $destinationpath);
    }

    /**
     * register_stream_wrapper
     * @return bool
     */
    public function register_stream_wrapper() {
        return true;
    }

    /**
     * get_md5_from_hash
     * @param mixed $contenthash
     *
     * @return string
     */
    private function get_md5_from_hash($contenthash) {
        $path = $this->get_fullpath_from_hash($contenthash);
        return md5_file($path);
    }

    /**
     * verify_object
     * @param string $contenthash
     * @param string $localpath
     *
     * @return bool
     */
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

    /**
     * test_connection
     * @return \stdClass
     */
    public function test_connection() {
        return (object)['success' => true, 'details' => ''];
    }

    /**
     * test_permissions
     * @param mixed $testdelete
     *
     * @return \stdClass
     */
    public function test_permissions($testdelete) {
        return (object)['success' => true, 'details' => ''];
    }

    /**
     * test_permissions
     * @return int
     */
    public function get_maximum_upload_size() {
        return $this->maxupload;
    }

    /**
     * Returns test expiry time.
     * @return int
     */
    public function get_token_expiry_time(): int {
        global $CFG;
        return $CFG->objectfs_phpunit_token_expiry_time;
    }
}

