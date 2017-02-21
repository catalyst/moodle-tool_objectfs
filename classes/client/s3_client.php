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
 * S3 client.
 *
 * @package   tool_objectfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\client;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/admin/tool/objectfs/sdk/aws-autoloader.php');

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

define('AWS_API_VERSION', '2006-03-01');

define('AWS_CAN_READ_OBJECT', 0);
define('AWS_CAN_WRITE_OBJECT', 1);
define('AWS_CAN_DELETE_OBJECT', 2);

class s3_client implements object_client {

    protected $client;
    protected $bucket;

    public function __construct($config) {
        $this->bucket = $config->bucket;
        $this->client = S3Client::factory(array(
                'credentials' => array('key' => $config->key, 'secret' => $config->secret),
                'region' => $config->region,
                'version' => AWS_API_VERSION
        ));
    }

    public function register_stream_wrapper() {
        // Registers 's3://bucket' as a prefix for file actions.
        $this->client->registerStreamWrapper();
    }


    public function check_object_md5($filekey, $expectedmd5) {
        $md5 = $this->get_object_md5_from_key($filekey);

        if ($md5 == $expectedmd5) {
            return true;
        }
        return false;
    }

    public function get_object_md5_from_key($objectkey) {
        try {
            $result = $this->client->headObject(array(
                            'Bucket' => $this->bucket,
                            'Key' => $objectkey));
        } catch (S3Exception $e) {
            return false;
        }

        $md5 = trim($result['ETag'], '"'); // Strip quotation marks.

        return $md5;
    }

    /**
     * Returns s3 fullpath to use with php file functions.
     *
     * @param  string $contenthash contenthash used as key in s3.
     * @return string fullpath to s3 object.
     */
    public function get_object_fullpath_from_hash($contenthash) {
        $l1 = $contenthash[0] . $contenthash[1];
        $l2 = $contenthash[2] . $contenthash[3];
        $filepath = $this->get_object_filepath_from_hash($contenthash);
        return "s3://$this->bucket/$filepath";
    }

    protected function get_object_filepath_from_hash($contenthash) {
        $l1 = $contenthash[0] . $contenthash[1];
        $l2 = $contenthash[2] . $contenthash[3];
        return "$l1/$l2/$contenthash";
    }

    /**
     * Tests connection to S3 and bucket.
     * There is no check connection in the AWS API.
     * We use list buckets instead and check the bucket is in the list.
     *
     * @return boolean true on success, false on failure.
     */
    public function test_connection() {
        try {
            $result = $this->client->headBucket(array(
                            'Bucket' => $this->bucket));
            return true;
        } catch (S3Exception $e) {
            return false;
        }
    }

    /**
     * Tests connection to S3 and bucket.
     * There is no check connection in the AWS API.
     * We use list buckets instead and check the bucket is in the list.
     *
     * @return boolean true on success, false on failure.
     */
    public function permissions_check() {

        $permissions = array();

        try {
            $result = $this->client->putObject(array(
                            'Bucket' => $this->bucket,
                            'Key' => 'permissions_check_file',
                            'Body' => 'test content'));
            $permissions[AWS_CAN_WRITE_OBJECT] = true;
        } catch (S3Exception $e) {
            $permissions[AWS_CAN_WRITE_OBJECT] = false;
        }

        try {
            $result = $this->client->getObject(array(
                            'Bucket' => $this->bucket,
                            'Key' => 'permissions_check_file'));
            $permissions[AWS_CAN_READ_OBJECT] = true;
        } catch (S3Exception $e) {
            if ($e->getAwsErrorCode() === 'NoSuchKey') {
                // Write could have failed.
                $permissions[AWS_CAN_READ_OBJECT] = true;
            } else {
                $permissions[AWS_CAN_READ_OBJECT] = false;
            }
        }

        try {
            $result = $this->client->deleteObject(array(
                            'Bucket' => $this->bucket,
                            'Key' => 'permissions_check_file'));
            $permissions[AWS_CAN_DELETE_OBJECT] = true;
        } catch (S3Exception $e) {
            $permissions[AWS_CAN_DELETE_OBJECT] = false;
        }

        return $permissions;
    }
}
