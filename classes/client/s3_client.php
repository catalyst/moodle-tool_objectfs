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

$autoloader = $CFG->dirroot . '/local/aws/sdk/aws-autoloader.php';

if (!file_exists($autoloader)) {

    // Stub class with bare implementation for when the SDK prerequisite does not exist.
    class s3_client {
        public function get_availability() {
            return false;
        }

        public function register_stream_wrapper() {
            return false;
        }
    }

    return;
}

require_once($autoloader);

use Aws\S3\MultipartUploader;
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
        $this->bucket = $config->s3_bucket;
        $this->set_client($config);
    }

    public function __sleep() {
        return array('bucket');
    }

    public function __wakeup() {
        // We dont want to store credentials in the client itself as
        // it will be serialised, so re-retrive them now.
        $config = get_objectfs_config();
        $this->set_client($config);
        $this->client->registerStreamWrapper();
    }

    public function set_client($config) {
        $this->client = S3Client::factory(array(
        'credentials' => array('key' => $config->s3_key, 'secret' => $config->s3_secret),
        'region' => $config->s3_region,
        'version' => AWS_API_VERSION
        ));
    }

    public function get_availability() {
        return true;
    }

    public function get_maximum_upload_size() {
        return PHP_INT_MAX;
    }

    public function register_stream_wrapper() {
        // Registers 's3://bucket' as a prefix for file actions.
        $this->client->registerStreamWrapper();
    }

    private function get_md5_from_hash($contenthash) {
        try {
            $key = $this->get_filepath_from_hash($contenthash);
            $result = $this->client->headObject(array(
                            'Bucket' => $this->bucket,
                            'Key' => $key));
        } catch (S3Exception $e) {
            return false;
        }

        $md5 = trim($result['ETag'], '"'); // Strip quotation marks.

        return $md5;
    }

    public function verify_object($contenthash, $localpath) {
        $localmd5 = md5_file($localpath);
        $externalmd5 = $this->get_md5_from_hash($contenthash);
        if ($externalmd5) {
            return true;
        }
        return false;
    }

    /**
     * Returns s3 fullpath to use with php file functions.
     *
     * @param  string $contenthash contenthash used as key in s3.
     * @return string fullpath to s3 object.
     */
    public function get_fullpath_from_hash($contenthash) {
        $filepath = $this->get_filepath_from_hash($contenthash);
        return "s3://$this->bucket/$filepath";
    }

    /**
     * S3 file streams require a seekable context to be supplied
     * if they are to be seekable.
     *
     * @return void
     */
    public function get_seekable_stream_context() {
        $context = stream_context_create(array(
            's3' => array(
                'seekable' => true
            )
        ));
        return $context;
    }

    public function get_filepath_from_hash($contenthash) {
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
        $connection = new \stdClass();
        $connection->success = true;
        $connection->message = '';

        try {
            $result = $this->client->headBucket(array(
                            'Bucket' => $this->bucket));

            $connection->message = get_string('settings:connectionsuccess', 'tool_objectfs');
        } catch (S3Exception $e) {
            $connection->success = false;
            $details = $this->get_exception_details($e);
            $connection->message = get_string('settings:connectionfailure', 'tool_objectfs') . $details;
        }
        return $connection;
    }

    /**
     * Tests connection to S3 and bucket.
     * There is no check connection in the AWS API.
     * We use list buckets instead and check the bucket is in the list.
     *
     * @return boolean true on success, false on failure.
     */
    public function test_permissions() {
        $permissions = new \stdClass();
        $permissions->success = true;
        $permissions->messages = array();

        try {
            $result = $this->client->putObject(array(
                            'Bucket' => $this->bucket,
                            'Key' => 'permissions_check_file',
                            'Body' => 'test content'));
        } catch (S3Exception $e) {
            $details = $this->get_exception_details($e);
            $permissions->messages[] = get_string('settings:writefailure', 'tool_objectfs') . $details;
            $permissions->success = false;
        }

        try {
            $result = $this->client->getObject(array(
                            'Bucket' => $this->bucket,
                            'Key' => 'permissions_check_file'));
        } catch (S3Exception $e) {
            $errorcode = $e->getAwsErrorCode();
            // Write could have failed.
            if ($errorcode !== 'NoSuchKey') {
                $details = $this->get_exception_details($e);
                $permissions->messages[] = get_string('settings:readfailure', 'tool_objectfs') . $details;
                $permissions->success = false;
            }
        }

        try {
            $result = $this->client->deleteObject(array(
                            'Bucket' => $this->bucket,
                            'Key' => 'permissions_check_file'));
            $permissions->messages[] = get_string('settings:deletesuccess', 'tool_objectfs');
            $permissions->success = false;
        } catch (S3Exception $e) {
            $errorcode = $e->getAwsErrorCode();
            // Something else went wrong.
            if ($errorcode !== 'AccessDenied') {
                $details = $this->get_exception_details($e);
                $permissions->messages[] = get_string('settings:deleteerror', 'tool_objectfs') . $details;
            }
        }

        if ($permissions->success) {
            $permissions->messages[] = get_string('settings:permissioncheckpassed', 'tool_objectfs');
        }

        return $permissions;
    }

    protected function get_exception_details($exception) {
        $message = $exception->getMessage();

        if (get_class($exception) !== 'S3Exception') {
            return "Not a S3 exception : $message";
        }

        $errorcode = $exception->getAwsErrorCode();

        $details = ' ';

        if ($message) {
            $details .= "ERROR MSG: " . $message . "\n";
        }

        if ($errorcode) {
            $details .= "ERROR CODE: " . $errorcode . "\n";
        }

        return $details;
    }

    public function define_amazon_s3_check($mform, $config) {
        global $OUTPUT;
        $connection = false;

        $client = new s3_client($config);
        $connection = $client->test_connection();

        if ($connection->success) {
            $mform->addElement('html', $OUTPUT->notification($connection->message, 'notifysuccess'));

            // Check permissions if we can connect.
            $permissions = $client->test_permissions();
            if ($permissions->success) {
                $mform->addElement('html', $OUTPUT->notification($permissions->messages[0], 'notifysuccess'));
            } else {
                foreach ($permissions->messages as $message) {
                    $mform->addElement('html', $OUTPUT->notification($message, 'notifyproblem'));
                }
            }

        } else {
            $mform->addElement('html', $OUTPUT->notification($connection->message, 'notifyproblem'));
            $permissions = false;
        }
        return $mform;
    }

    public function define_client_section($mform, $config) {

        $mform->addElement('header', 'awsheader', get_string('settings:aws:header', 'tool_objectfs'));
        $mform->setExpanded('awsheader');

        $mform = $this->define_amazon_s3_check($mform, $config);

        $regionoptions = array(
            'us-east-1'      => 'us-east-1 (N. Virginia)',
            'us-east-2'      => 'us-east-2 (Ohio)',
            'us-west-1'      => 'us-west-1 (N. California)',
            'us-west-2'      => 'us-west-2 (Oregon)',
            'ap-northeast-1' => 'ap-northeast-1 (Tokyo)',
            'ap-northeast-2' => 'ap-northeast-2 (Seoul)',
            'ap-northeast-3' => 'ap-northeast-3 (Osaka)',
            'ap-south-1'     => 'ap-south-1 (Mumbai)',
            'ap-southeast-1' => 'ap-southeast-1 (Singapore)',
            'ap-southeast-2' => 'ap-southeast-2 (Sydney)',
            'ca-central-1'   => 'ca-central-1 (Canda Central)',
            'cn-north-1'     => 'cn-north-1 (Beijing)',
            'cn-northwest-1' => 'cn-northwest-1 (Ningxia)',
            'eu-central-1'   => 'eu-central-1 (Frankfurt)',
            'eu-west-1'      => 'eu-west-1 (Ireland)',
            'eu-west-2'      => 'eu-west-2 (London)',
            'eu-west-3'      => 'eu-west-3 (Paris)',
            'sa-east-1'      => 'sa-east-1 (Sao Paulo)'
        );

        $mform->addElement('text', 's3_key', get_string('settings:aws:key', 'tool_objectfs'));
        $mform->addHelpButton('s3_key', 'settings:aws:key', 'tool_objectfs');
        $mform->setType("s3_key", PARAM_TEXT);

        $mform->addElement('passwordunmask', 's3_secret', get_string('settings:aws:secret', 'tool_objectfs'), array('size' => 40));
        $mform->addHelpButton('s3_secret', 'settings:aws:secret', 'tool_objectfs');
        $mform->setType("s3_secret", PARAM_TEXT);

        $mform->addElement('text', 's3_bucket', get_string('settings:aws:bucket', 'tool_objectfs'));
        $mform->addHelpButton('s3_bucket', 'settings:aws:bucket', 'tool_objectfs');
        $mform->setType("s3_bucket", PARAM_TEXT);

        $mform->addElement('select', 's3_region', get_string('settings:aws:region', 'tool_objectfs'), $regionoptions);
        $mform->addHelpButton('s3_region', 'settings:aws:region', 'tool_objectfs');
        return $mform;
    }

    public function copy_object_from_local_to_external_path($localpath, $contenthash) {

        $externalpath = $this->get_filepath_from_hash($contenthash);

        $uploader = new MultipartUploader($this->client, $localpath, [
            'bucket' => $this->bucket,
            'key'    => $externalpath,
        ]);

        try {
            $result = $uploader->upload();
            echo "Upload complete: {$result['ObjectURL']}\n";
            return $result;
        } catch (MultipartUploadException $e) {
            echo $e->getMessage() . "\n";
        }
    }
}
