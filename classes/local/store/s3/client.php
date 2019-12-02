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

namespace tool_objectfs\local\store\s3;

defined('MOODLE_INTERNAL') || die();

use Aws\Exception\MultipartUploadException;
use Aws\S3\MultipartUploader;
use Aws\S3\ObjectUploader;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use Aws\CloudFront\CloudFrontClient;
use tool_objectfs\local\store\object_client_base;


define('AWS_API_VERSION', '2006-03-01');
define('AWS_CAN_READ_OBJECT', 0);
define('AWS_CAN_WRITE_OBJECT', 1);
define('AWS_CAN_DELETE_OBJECT', 2);

class client extends object_client_base {
    protected $client;
    protected $bucket;

    public function __construct($config) {
        global $CFG;
        $this->autoloader = $CFG->dirroot . '/local/aws/sdk/aws-autoloader.php';

        if ($this->get_availability() && !empty($config)) {
            require_once($this->autoloader);
            $this->bucket = $config->s3_bucket;
            $this->expirationtime = $config->expirationtime;
            $this->presignedminfilesize = $config->presignedminfilesize;
            $this->enablepresignedurls = $config->enablepresignedurls;
            $this->enablepresignedcloudfronturls = $config->enablepresignedcloudfronturls;
            $this->set_client($config);
        } else {
            parent::__construct($config);
        }
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

    public function get_maximum_upload_size() {
        // Using the multipart upload methods , you can upload objects from 5 MB to 5 TB in size.
        // See https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/s3-multipart-upload.html.
        return OBJECTFS_BYTES_IN_TERABYTE * 5;
    }

    /**
     * Registers 's3://bucket' as a prefix for file actions.
     *
     */
    public function register_stream_wrapper() {
        if ($this->get_availability()) {
            $this->client->registerStreamWrapper();
        } else {
            parent::register_stream_wrapper();
        }
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
     * Returns s3 trash fullpath to use with php file functions.
     *
     * @param  string $contenthash contenthash used as key in s3.
     * @return string trash fullpath to s3 object.
     */
    public function get_trash_fullpath_from_hash($contenthash) {
        $filepath = $this->get_filepath_from_hash($contenthash);
        return "s3://$this->bucket/trash/$filepath";
    }

    /**
     * Deletes a file in S3 storage.
     *
     * @path   string full path to S3 file.
     */
    public function delete_file($fullpath) {
        unlink($fullpath);
    }

    /**
     * Moves a file in S3 storage.
     *
     * @param string $currentpath     current full path to S3 file.
     * @param string $destinationpath destination path.
     */
    public function rename_file($currentpath, $destinationpath) {
        rename($currentpath, $destinationpath);
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

    protected function get_filepath_from_hash($contenthash) {
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
    public function test_permissions($testdelete) {
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
            $permissions->messages[get_string('settings:writefailure', 'tool_objectfs') . $details] = 'notifyproblem';
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
                $permissions->messages[get_string('settings:readfailure', 'tool_objectfs') . $details] = 'notifyproblem';
                $permissions->success = false;
            }
        }

        if ($testdelete) {
            try {
                $result = $this->client->deleteObject(array('Bucket' => $this->bucket, 'Key' => 'permissions_check_file'));
                $permissions->messages[get_string('settings:deletesuccess', 'tool_objectfs')] = 'warning';
                $permissions->success = false;
            } catch (S3Exception $e) {
                $errorcode = $e->getAwsErrorCode();
                // Something else went wrong.
                if ($errorcode !== 'AccessDenied') {
                    $details = $this->get_exception_details($e);
                    $permissions->messages[get_string('settings:deleteerror', 'tool_objectfs') . $details] = 'notifyproblem';
                    $permissions->success = false;
                }
            }
        }

        if ($permissions->success) {
            $permissions->messages[get_string('settings:permissioncheckpassed', 'tool_objectfs')] = 'notifysuccess';
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

    public function define_amazon_s3_check($mform, $testdelete = true) {
        global $OUTPUT;
        $connection = false;

        $connection = $this->test_connection();

        if ($connection->success) {
            $mform->addElement('html', $OUTPUT->notification($connection->message, 'notifysuccess'));

            // Check permissions if we can connect.
            $permissions = $this->test_permissions($testdelete);
            if ($permissions->success) {
                $mform->addElement('html', $OUTPUT->notification(key($permissions->messages), current($permissions->messages)));
            } else {
                foreach ($permissions->messages as $message => $type) {
                    $mform->addElement('html', $OUTPUT->notification($message, $type));
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

        $mform = $this->define_amazon_s3_check($mform);

        return $mform;
    }

    /**
     * Upload a file from the local path to s3 bucket.
     *
     * @param string $localpath Path to a local file.
     * @param string $contenthash Content hash of the file.
     *
     * @throws \Exception if fails.
     */
    public function upload_to_s3($localpath, $contenthash) {
        $filehandle = fopen($localpath, 'rb');

        if (!$filehandle) {
            throw new \Exception('Can not open the file for reading: ' . $localpath);
        }

        try {
            $externalpath = $this->get_filepath_from_hash($contenthash);
            $uploader = new ObjectUploader($this->client, $this->bucket, $externalpath, $filehandle);
            $uploader->upload();
            fclose($filehandle);
        } catch (MultipartUploadException $e) {
            $params = $e->getState()->getId();
            $this->client->abortMultipartUpload($params);
            fclose($filehandle);

            throw new \Exception($e->getMessage());
        }
    }

    /**
     * Does the storage support pre-signed URLs.
     *
     * @return bool.
     */
    public function support_presigned_urls() {
        return true;
    }

    /**
     * Generates pre-signed URL to S3 file from its hash.
     *
     * @param string $contenthash file content hash.
     * @param array $headers request headers.
     *
     * @return string.
     */
    public function generate_presigned_url($contenthash, $headers) {
        // Refactor this when CDN plugin exists (and/or Cloudfront is not the only CDN to use).
        if ($this->enablepresignedcloudfronturls) {
            return $this->generate_presigned_cdn_url($contenthash, $headers, 'cloudfront');
        }

        $key = $this->get_filepath_from_hash($contenthash);
        $params['Bucket'] = $this->bucket;
        $params['Key'] = $key;

        $contentdisposition = $this->get_header($headers, 'Content-Disposition');
        if ($contentdisposition !== '') {
            $params['ResponseContentDisposition'] = $contentdisposition;
        }

        $contenttype = $this->get_header($headers, 'Content-Type');
        if ($contenttype !== '') {
            $params['ResponseContentType'] = $contenttype;
        }

        $command = $this->client->getCommand('GetObject', $params);
        $expirationtime = $this->get_header($headers, 'Expires');

        if ($expirationtime !== '' and strtotime($expirationtime) != 0) {
            $request = $this->client->createPresignedRequest($command, $expirationtime);
        } else {
            $request = $this->client->createPresignedRequest($command, '+' . $this->expirationtime . ' seconds');
        }

        $signedurl = (string)$request->getUri();

        return $signedurl;
    }

    /**
     * Generates pre-signed URL to CDN from its hash.
     *
     * @param string $contenthash file content hash.
     * @param array $headers request headers.
     * @param string $cdn CDN shortname (default: cloudfront).
     * @param bool $nicefilename deliver original filename rather than origin hashed filename.
     *
     * @return string.
     */
    public function generate_presigned_cdn_url($contenthash = '', $headers = array(), $cdn = 'cloudfront', $nicefilename = true) {

        switch ($cdn)
        {
            case 'cloudfront':

                if (!$this->enablepresignedcloudfronturls) {
                    return ''; // Throw an exception - called but not enabled.
                }

                $cdnconfig = get_objectfs_config();
                $key = $this->get_filepath_from_hash($contenthash);
                $cloudfrontclient = new CloudFrontClient(
                    array(
                        'profile' => 'default',
                        'version' => 'latest', /* '2014-11-06' */
                        'region' => $cdnconfig->s3_region,  /* The region is the source bucket region ? - 'ap-southeast-2' */
                    )
                );

                $resourcedomain = $cdnconfig->cloudfront_resource_domain;

                $resourcekey = $resourcedomain . '/' . $key;

                if ($nicefilename) {
                    // We are trying to deliver original filename rather than hash filename to client.

                    $contentdisposition = '';
                    $originalfilename = '';
                    $originalcontenttype = '';

                    $contentdisposition = $this->get_header($headers, 'Content-Disposition');
                    if ($contentdisposition !== '') {
                        $contentdisposition = trim($contentdisposition); // S3 $params['ResponseContentDisposition'].
                    }

                    $contenttype = $this->get_header($headers, 'Content-Type');
                    if ($contenttype !== '') {
                        $originalcontenttype = trim($contenttype); // S3 $params['ResponseContentType'].
                    }

                    /*
                        Need to get the filename and content-type from HEADERS array
                        Without invoking more DB hits (the header array contains it already by now).
                    */

                    if (!empty($contentdisposition)) {
                        $fparts = explode('; ', $contentdisposition);
                        $originalfilename = str_replace('filename=', '', $fparts[1]); // Get the actual filename.
                        $originalfilename = str_replace('"', '', $originalfilename); // Remove the quotes.
                        $contentdisposition = $fparts[0];

                        $newkey = $key .
                            '?response-content-disposition='.rawurlencode(
                                $contentdisposition . ';filename="' . utf8_encode($originalfilename) . '"'
                            ) .
                            '&response-content-type=' . rawurlencode($originalcontenttype);

                        // Alternative without filename: $newkey = $key . '?response-content-disposition='.rawurlencode($contentdisposition.';'.(utf8_encode($originalfilename)).'').'&response-content-type='.rawurlencode($originalcontenttype);.

                        $resourcekey = $resourcedomain . '/' . $newkey;
                    }
                }

                if (isset($cdnconfig->expirationtime) && !empty($cdnconfig->expirationtime)) {
                    $expirationvalue = time() + $cdnconfig->expirationtime;
                } else {
                    $expirationvalue = 0; // Example: time()+300.
                }
                $expires = time() + $expirationvalue;

                $signedurlcannedpolicy = $cloudfrontclient->getSignedUrl([
                    'url' => $resourcekey,
                    'expires' => $expires,
                    'key_pair_id' => $cdnconfig->cloudfront_key_pair_id,
                    'private_key' => realpath($cdnconfig->cloudfront_private_key_pem_file_pathname),
                    'ResponseContentDisposition' => $contentdisposition . ';filename=' . $originalfilename . '',
                    'ResponseFilename' => $originalfilename,
                ]);

                $signedurl = (string)$signedurlcannedpolicy;

                $headers[] = 'Location:"' . $signedurl . '"'; // This may cause loss of headers (etag for example).
                break;

            default:
                $signedurl = '';
        }

        return $signedurl;
    }


}
