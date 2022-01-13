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

use tool_objectfs\local\manager;
use tool_objectfs\local\store\object_client_base;
use tool_objectfs\local\store\signed_url;
use local_aws\admin_settings_aws_region;

define('AWS_API_VERSION', '2006-03-01');
define('AWS_CAN_READ_OBJECT', 0);
define('AWS_CAN_WRITE_OBJECT', 1);
define('AWS_CAN_DELETE_OBJECT', 2);

class client extends object_client_base {

    /**
     * @var int A predefined limit of data stored.
     * When hit, php://temp will use a temporary file.
     * Reference: https://github.com/catalyst/moodle-local_aws/blob/master/sdk/Aws/S3/StreamWrapper.php#L19-L25
     */
    const MAX_TEMP_LIMIT = 2097152;

    protected $client;

    public function __construct($config) {
        global $CFG;
        $this->autoloader = $CFG->dirroot . '/local/aws/sdk/aws-autoloader.php';
        $this->config = $config;

        if ($this->get_availability() && !empty($config)) {
            require_once($this->autoloader);
            // Using the multipart upload methods , you can upload objects from 5 MB to 5 TB in size.
            // See https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/s3-multipart-upload.html.
            $this->maxupload = OBJECTFS_BYTES_IN_TERABYTE * 5;
            $this->bucket = $config->s3_bucket;
            $this->expirationtime = $config->expirationtime;
            $this->presignedminfilesize = $config->presignedminfilesize;
            $this->enablepresignedurls = $config->enablepresignedurls;
            $this->signingmethod = $config->signingmethod;
            $this->bucketkeyprefix = $config->key_prefix;
            $this->cloudfrontresourcedomain = $config->cloudfrontresourcedomain;

            if ('cf' === $this->signingmethod) {
                if (!$this->cloudfrontresourcedomain) {
                    throw new \moodle_exception(OBJECTFS_PLUGIN_NAME . ': cloudfrontresourcedomain not configured');
                }
            }

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
        $config = manager::get_objectfs_config();
        $this->set_client($config);
        if ($this->is_functional()) {
            $this->client->registerStreamWrapper();
        }
    }

    /**
     * Check if the client is functional.
     * @return bool
     */
    protected function is_functional() {
        return isset($this->client);
    }

    /**
     * Check if the client configured properly.
     *
     * @param \stdClass $config Client config.
     * @return bool
     */
    protected function is_configured($config) {
        if (empty($config->s3_bucket)) {
            return false;
        }

        if (empty($config->s3_region)) {
            return false;
        }

        if (empty($config->s3_usesdkcreds) && (empty($config->s3_key) || empty($config->s3_secret))) {
            return false;
        }

        return true;
    }

    /**
     * Set the client.
     *
     * @param \stdClass $config Client config.
     */
    public function set_client($config) {
        if (!$this->is_configured($config)) {
            $this->client = null;
            return;
        }

        $options = array(
            'region' => $config->s3_region,
            'version' => AWS_API_VERSION
        );

        if (empty($config->s3_usesdkcreds)) {
            $options['credentials'] = array('key' => $config->s3_key, 'secret' => $config->s3_secret);
        }

        if ($config->useproxy) {
            $options['http'] = array('proxy' => $this->get_proxy_string());
        }

        // Support base_url config for aws api compatible endpoints.
        if ($config->s3_base_url) {
            $options['endpoint'] = $config->s3_base_url;
        }

        $this->client = \Aws\S3\S3Client::factory($options);
    }

    /**
     * Registers 's3://bucket' as a prefix for file actions.
     *
     */
    public function register_stream_wrapper() {
        if ($this->get_availability() && $this->is_functional()) {
            $this->client->registerStreamWrapper();
        } else {
            parent::register_stream_wrapper();
        }
    }

    private function get_md5_from_hash($contenthash) {
        if (!$this->is_functional()) {
            return false;
        }

        try {
            $key = $this->get_filepath_from_hash($contenthash);
            $result = $this->client->headObject(array(
                            'Bucket' => $this->bucket,
                            'Key' => $this->bucketkeyprefix . $key));
        } catch (\Aws\S3\Exception\S3Exception $e) {
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
        return "s3://$this->bucket/" . $this->bucketkeyprefix . $filepath;
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
     * @return object
     * @throws \coding_exception
     */
    public function test_connection() {
        $connection = new \stdClass();
        $connection->success = true;
        $connection->details = '';

        try {
            if (!$this->is_functional()) {
                $connection->success = false;
                $connection->details = get_string('settings:notconfigured', 'tool_objectfs');
            } else {
                $this->client->headBucket(array('Bucket' => $this->bucket));
            }
        } catch (\Aws\S3\Exception\S3Exception $e) {
            $connection->success = false;
            $connection->details = $this->get_exception_details($e);
        } catch (\GuzzleHttp\Exception\InvalidArgumentException $e) {
            $connection->success = false;
            $connection->details = $this->get_exception_details($e);
        } catch (\Aws\Exception\CredentialsException $e) {
            $connection->success = false;
            $connection->details = $this->get_exception_details($e);
        }

        return $connection;
    }

    /**
     * Tests connection to S3 and bucket.
     * There is no check connection in the AWS API.
     * We use list buckets instead and check the bucket is in the list.
     *
     * @return object
     * @throws \coding_exception
     */
    public function test_permissions($testdelete) {
        $permissions = new \stdClass();
        $permissions->success = true;
        $permissions->messages = array();

        if ($this->is_functional()) {
            $permissions->success = false;
            $permissions->messages = array();
            return $permissions;
        }

        try {
            $result = $this->client->putObject(array(
                'Bucket' => $this->bucket,
                'Key' => $this->bucketkeyprefix . 'permissions_check_file',
                'Body' => 'test content'));
        } catch (\Aws\S3\Exception\S3Exception $e) {
            $details = $this->get_exception_details($e);
            $permissions->messages[get_string('settings:writefailure', 'tool_objectfs') . $details] = 'notifyproblem';
            $permissions->success = false;
        }

        try {
            $result = $this->client->getObject(array(
                'Bucket' => $this->bucket,
                'Key' => $this->bucketkeyprefix . 'permissions_check_file'));
        } catch (\Aws\S3\Exception\S3Exception $e) {
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
                $result = $this->client->deleteObject(array('Bucket' => $this->bucket, 'Key' => $this->bucketkeyprefix . 'permissions_check_file'));
                $permissions->messages[get_string('settings:deletesuccess', 'tool_objectfs')] = 'warning';
                $permissions->success = false;
            } catch (\Aws\S3\Exception\S3Exception $e) {
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

        if (get_class($exception) !== '\Aws\S3\Exception\S3Exception') {
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

    /**
     * S3 settings form.
     *
     * @param  \admin_settingpage  $settings
     * @param  object              $config
     *
     * @return \admin_settingpage
     * @throws \coding_exception
     */
    public function define_client_section($settings, $config) {
        global $OUTPUT;
        $plugins = \core_component::get_plugin_list('local');

        if (!array_key_exists('aws', $plugins)) {
            $text  = $OUTPUT->notification(new \lang_string('settings:aws:installneeded', OBJECTFS_PLUGIN_NAME));
            $settings->add(new \admin_setting_heading('tool_objectfs/aws',
                new \lang_string('settings:aws:header', 'tool_objectfs'), $text));
            return $settings;
        }

        $plugin = (object)['version' => null];
        if (file_exists($plugins['aws'].'/version.php')) {
            include($plugins['aws'].'/version.php');
        }
        if (empty($plugin->version) || $plugin->version < 2020051200) {
            $text  = $OUTPUT->notification(new \lang_string('settings:aws:upgradeneeded', OBJECTFS_PLUGIN_NAME));
            $settings->add(new \admin_setting_heading('tool_objectfs/aws',
                new \lang_string('settings:aws:header', 'tool_objectfs'), $text));
            return $settings;
        }

        $settings->add(new \admin_setting_heading('tool_objectfs/aws',
            new \lang_string('settings:aws:header', 'tool_objectfs'), $this->define_client_check()));

        $settings->add(new \admin_setting_configcheckbox('tool_objectfs/s3_usesdkcreds',
            new \lang_string('settings:aws:usesdkcreds', 'tool_objectfs'),
            $this->define_client_check_sdk($config), ''));

        if (empty($config->s3_usesdkcreds)) {
            $settings->add(new \admin_setting_configtext('tool_objectfs/s3_key',
                new \lang_string('settings:aws:key', 'tool_objectfs'),
                new \lang_string('settings:aws:key_help', 'tool_objectfs'), ''));

            $settings->add(new \admin_setting_configpasswordunmask('tool_objectfs/s3_secret',
                new \lang_string('settings:aws:secret', 'tool_objectfs'),
                new \lang_string('settings:aws:secret_help', 'tool_objectfs'), ''));
        }

        $settings->add(new \admin_setting_configtext('tool_objectfs/s3_bucket',
            new \lang_string('settings:aws:bucket', 'tool_objectfs'),
            new \lang_string('settings:aws:bucket_help', 'tool_objectfs'), ''));

        $settings->add(new admin_settings_aws_region('tool_objectfs/s3_region',
            new \lang_string('settings:aws:region', 'tool_objectfs'),
            new \lang_string('settings:aws:region_help', 'tool_objectfs'), ''));

        $settings->add(new \admin_setting_configtext('tool_objectfs/s3_base_url',
            new \lang_string('settings:aws:base_url', 'tool_objectfs'),
            new \lang_string('settings:aws:base_url_help', 'tool_objectfs'), ''));

        $settings->add(new \admin_setting_configtext('tool_objectfs/key_prefix',
            new \lang_string('settings:aws:key_prefix', 'tool_objectfs'),
            new \lang_string('settings:aws:key_prefix_help', 'tool_objectfs'), ''));

        return $settings;
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
            $uploader = new \Aws\S3\ObjectUploader($this->client, $this->bucket, $this->bucketkeyprefix . $externalpath, $filehandle);
            $uploader->upload();
            fclose($filehandle);
        } catch (\Aws\Exception\MultipartUploadException $e) {
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
     * @return signed_url
     * @throws \Exception
     */
    public function generate_presigned_url($contenthash, $headers = array()) {
        if ('cf' === $this->signingmethod) {
            return  $this->generate_presigned_url_cloudfront($contenthash, $headers);
        }
        return  $this->generate_presigned_url_s3($contenthash, $headers);
    }

    /**
     * @param string $contenthash
     * @param array $headers
     * @return signed_url
     */
    private function generate_presigned_url_s3($contenthash, $headers) {
        $contentdisposition = manager::get_header($headers, 'Content-Disposition');
        if ($contentdisposition !== '') {
            $params['ResponseContentDisposition'] = $contentdisposition;
        }

        $contenttype = manager::get_header($headers, 'Content-Type');
        if ($contenttype !== '') {
            $params['ResponseContentType'] = $contenttype;
        }

        $key = $this->get_filepath_from_hash($contenthash);
        $params['Bucket'] = $this->bucket;
        $params['Key'] = $this->bucketkeyprefix . $key;

        $contentdisposition = manager::get_header($headers, 'Content-Disposition');
        if ($contentdisposition !== '') {
            $params['ResponseContentDisposition'] = $contentdisposition;
        }

        $contenttype = manager::get_header($headers, 'Content-Type');
        if ($contenttype !== '') {
            $params['ResponseContentType'] = $contenttype;
        }

        $command = $this->client->getCommand('GetObject', $params);
        $expires = $this->get_expiration_time(time(), manager::get_header($headers, 'Expires'));
        $request = $this->client->createPresignedRequest($command, $expires);

        $signedurl = (string)$request->getUri();
        return new signed_url(new \moodle_url($signedurl), $expires);
    }

    /**
     * @param string $contenthash
     * @param array $headers
     * @param bool $nicefilename
     * @return signed_url
     * @throws \Exception
     */
    private function generate_presigned_url_cloudfront($contenthash, array $headers = [], $nicefilename = true) {
        $key = $this->get_filepath_from_hash($contenthash);

        $expires = $this->get_expiration_time(time(), manager::get_header($headers, 'Expires'));

        if ($nicefilename) {
            $key .= $this->get_nice_filename($headers);
        }
        $resource = $this->cloudfrontresourcedomain . '/' . $key;
        // This is the id of the Cloudfront key pair you generated.
        $keypairid = $this->config->cloudfrontkeypairid;

        $signingpolicy = [
            'Statement' => [[
                'Resource' => $resource,
                'Condition' => [
                    'DateLessThan' => ['AWS:EpochTime' => $expires],
                ],
            ]],
        ];
        $json = json_encode($signingpolicy, JSON_UNESCAPED_SLASHES);

        // Create the private key.
        $key = manager::parse_cloudfront_private_key($this->config->cloudfrontprivatekey);
        if (!$key) {
            throw new \moodle_exception(OBJECTFS_PLUGIN_NAME . ': could not load cloudfront signing key.');
        }

        // Sign the policy with the private key.
        if (!openssl_sign($json, $signedpolicy, $key, OPENSSL_ALGO_SHA1)) {
            throw new \moodle_exception(OBJECTFS_PLUGIN_NAME . ': signing policy failed, ' . openssl_error_string());
        }

        // Create url safe signed policy.
        $base64signedpolicy = base64_encode($signedpolicy);
        $signature = str_replace(['+', '=', '/'], ['-', '_', '~'], $base64signedpolicy);

        // Construct the URL.
        $params = ['Expires' => $expires, 'Signature' => $signature, 'Key-Pair-Id' => $keypairid];
        return new signed_url(new \moodle_url($resource, $params), $expires);
    }

    /**
     * @param $headers
     * @return string
     */
    private function get_nice_filename($headers) {
        // We are trying to deliver original filename rather than hash filename to client.
        $originalfilename = '';
        $contentdisposition = trim(manager::get_header($headers, 'Content-Disposition'));
        $originalcontenttype = trim(manager::get_header($headers, 'Content-Type'));

        /*
            Need to get the filename and content-type from HEADERS array
            Without invoking more DB hits (the header array contains it already by now).
        */

        if (!empty($contentdisposition)) {
            $fparts = explode('; ', $contentdisposition);
            if (!empty($fparts[1])) {
                $originalfilename = str_replace('filename=', '', $fparts[1]); // Get the actual filename.
                $originalfilename = str_replace('"', '', $originalfilename); // Remove the quotes.
            }
            if (!empty($fparts[0])) {
                $contentdisposition = $fparts[0];
            }

            if (!empty($originalfilename)) {
                return '?response-content-disposition=' .
                    rawurlencode($contentdisposition . ';filename="' . utf8_encode($originalfilename) . '"') .
                    '&response-content-type=' . rawurlencode($originalcontenttype);
            }
        }
        return '';
    }

    /**
     * Returns expiration timestamp for pre-signed url.
     *
     * @param  int   $now     Now timestamp
     * @param  mixed $expires 'Expires' header
     * @return int
     */
    public function get_expiration_time($now, $expires) {
        if (is_string($expires)) {
            // Convert to a valid timestamp.
            $expires = strtotime($expires);
        }
        // Invalid or already expired:
        // If it's set to 0 or strtotime() returned false,
        // set it to default + 1 min as a healthy margin.
        if (empty($expires)) {
            $expires = $now + $this->expirationtime + MINSECS;
        }
        // Expiry too short, push it out to the next 2 minutes (will round down later):
        // If it's already expired or expires less than 2 minutes, set it to 2
        // minutes. This works together with rounding down later on to ensure a
        // non zero expiry time, and a minimum expiry of 1 minute.
        if ($expires < $now + (2 * MINSECS)) {
            $expires = $now + (2 * MINSECS);
        }
        // Checks upper bound:
        // The expiration date of a signature version 4 presigned URL must be
        // less than one week. So if it's greater than a week set it to 1 week.
        // Use MINSECS as a healthy margin of error.
        if ($expires - $now > WEEKSECS - MINSECS) {
            $expires = $now + WEEKSECS - MINSECS;
        }
        // Rounds (down) to nearest minute:
        // With our new expiry time, ensure we round down to the nearest minute
        // (#457) to ensure expiry of potentially the same file will use the
        // same URL, and will result in less duplicate requests.
        $expires -= ($expires % MINSECS);

        return $expires;
    }

    /**
     * Returns proxy string to use as a storage client param.
     * String format: 'username:password@127.0.0.1:123'.
     *
     * @return string
     */
    public function get_proxy_string() {
        global $CFG;
        $proxy = '';
        if (empty($CFG->proxytype) || $CFG->proxytype == 'SOCKS5') {
            // S3 doesn't support SOCKS proxy.
            return $proxy;
        }
        if (!empty($CFG->proxyhost)) {
            $proxy = $CFG->proxyhost;
            if (!empty($CFG->proxyport)) {
                $proxy .= ':'. $CFG->proxyport;
            }
            if (!empty($CFG->proxyuser) && !empty($CFG->proxypassword)) {
                $proxy = $CFG->proxyuser . ':' . $CFG->proxypassword . '@' . $proxy;
            }
        }
        return $proxy;
    }

    /**
     * Perform test connection and permission check using
     * the default credential provider chain to find AWS credentials.
     *
     * @param  object $config
     * @return string HTML string holding notification messages
     * @throws /coding_exception
     */
    public function define_client_check_sdk($config) {
        global $OUTPUT;
        $output = '';
        if (empty($config->s3_usesdkcreds)) {
            $config->s3_usesdkcreds = 1;
            $this->set_client($config);
            $connection = $this->test_connection();
            if ($connection->success) {
                $output .= $OUTPUT->notification(get_string('settings:aws:sdkcredsok', 'tool_objectfs'), 'notifysuccess');
                // Check permissions if we can connect.
                $permissions = $this->test_permissions($this->testdelete);
                if ($permissions->success) {
                    $output .= $OUTPUT->notification(key($permissions->messages), 'notifysuccess');
                } else {
                    foreach ($permissions->messages as $message => $type) {
                        $output .= $OUTPUT->notification($message, $type);
                    }
                }
            } else {
                $output .= $OUTPUT->notification(get_string('settings:aws:sdkcredserror', 'tool_objectfs'), 'warning');
            }
            $config->s3_usesdkcreds = 0;
            $this->set_client($config);
        }
        return $output;
    }

    /**
     * Proxy range request.
     *
     * @param  \stored_file $file    The file to send
     * @param  object       $ranges  Object with rangefrom, rangeto and length properties.
     * @return false If couldn't get data.
     * @throws \coding_exception
     * @throws \file_exception
     */
    public function proxy_range_request(\stored_file $file, $ranges) {
        // Do not serve files if the feature is disabled or if the file size is less than 2MB.
        if (empty($this->config->proxyrangerequests) || $file->get_filesize() < self::MAX_TEMP_LIMIT) {
            return false;
        }

        $response = $this->curl_range_request_to_presigned_url($file->get_contenthash(), $ranges, headers_list());
        $httpcode = manager::get_header($response['responseheaders'], 'HTTP/1.1');

        if ($response['content'] != '' && $httpcode == '206 Partial Content') {
            header('HTTP/1.1 206 Partial Content');
            header('Accept-Ranges: bytes');
            header('Content-Range: ' . manager::get_header($response['responseheaders'], 'Content-Range'));
            $contentlength = manager::get_header($response['responseheaders'], 'Content-Length');
            if ($contentlength !== '') {
                header('Content-Length: ' . $contentlength);
            }
            echo $response['content'];
            die;
        } else {
            throw new \file_exception('Range request to URL ' . $response['url'] .
                ' failed with HTTP code: ' . $httpcode . '. Details: ' . $response['content']);
        }
    }

    /**
     * Does the range request to Pre-Signed URL via cURL.
     *
     * @param  string $contenthash File content hash.
     * @param  object $ranges      Object with rangefrom, rangeto and length properties.
     * @param  array  $headers     Request headers.
     * @return array               Requested data.
     * @throws \coding_exception
     */
    public function curl_range_request_to_presigned_url($contenthash, $ranges, $headers) {
        try {
            $signedurl = $this->generate_presigned_url_s3($contenthash, $headers);
            $url = $signedurl->url->out(false);
        } catch (\Exception $e) {
            throw new \coding_exception('Failed to generate pre-signed url: ' . $e->getMessage());
        }
        $headers = array(
            'HTTP/1.1 206 Partial Content',
            'Content-Length: '. $ranges->length,
            'Range: bytes=' . $ranges->rangefrom . '-' . $ranges->rangeto,
        );
        $curl = new \curl();
        $curl->setopt(array('CURLOPT_RETURNTRANSFER' => true));
        $curl->setopt(array('CURLOPT_SSL_VERIFYPEER' => false));
        $curl->setopt(array('CURLOPT_CONNECTTIMEOUT' => 15));
        $curl->setopt(array('CURLOPT_TIMEOUT' => 15));
        $curl->setHeader($headers);
        $content = $curl->get($url);
        return array('responseheaders' => $curl->getResponse(), 'content' => $content, 'url' => $url);
    }

    /**
     * Test proxy range request.
     *
     * @param  object  $filesystem  Filesystem to be tested.
     * @return object
     * @throws \coding_exception
     */
    public function test_range_request($filesystem) {
        global $PAGE;
        $output = $PAGE->get_renderer('tool_objectfs');
        $testfiles = $output->presignedurl_tests_load_files($filesystem);
        foreach ($testfiles as $file) {
            if ($file->get_filename() == 'testvideo.mp4') {
                $ranges = (object)['rangefrom' => 0, 'rangeto' => 999, 'length' => 1000];
                $response = $this->curl_range_request_to_presigned_url($file->get_contenthash(),
                    $ranges, ['Expires' => time() + HOURSECS]);
                $httpcode = manager::get_header($response['responseheaders'], 'HTTP/1.1');
                if ($response['content'] != '' && $httpcode == '206 Partial Content') {
                    return (object)['result' => true];
                } else {
                    $a = (object)['url' => $response['url'], 'httpcode' => $httpcode, 'details' => $response['content']];
                    return (object)['result' => false, 'error' => get_string('rangerequestfailed', 'tool_objectfs', $a)];
                }
            }
        }
        return (object)['result' => false, 'error' => get_string('fixturefilemissing', 'tool_objectfs')];
    }
}
