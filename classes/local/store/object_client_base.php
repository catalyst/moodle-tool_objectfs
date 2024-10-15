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
 * Object client abstract class.
 *
 * @package   tool_objectfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\local\store;

/**
 * [Description object_client_base]
 */
abstract class object_client_base implements object_client {

    /**
     * @var string
     */
    protected $autoloader;
    /**
     * @var mixed
     */
    protected $expirationtime;
    /**
     * @var int
     */
    public $presignedminfilesize;
    /**
     * @var mixed
     */
    public $enablepresignedurls;

    /** @var int $maxupload Maximum allowed file size that can be uploaded. */
    protected $maxupload;

    /** @var object $config Client config. */
    protected $config;

    /**
     * If deletion should be tested.
     * Only tested when the deleteexternal setting is not set to 'no'
     * @return bool
     */
    public function should_test_delete(): bool {
        return !empty($this->config) && $this->config->deleteexternal != TOOL_OBJECTFS_DELETE_EXTERNAL_NO;
    }

    /**
     * construct
     * @param \stdClass $config
     */
    public function __construct($config) {

    }

    /**
     * Returns true if the Client SDK exists and has been loaded.
     *
     * @return bool
     */
    public function get_availability() {
        if (file_exists($this->autoloader)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * register_stream_wrapper
     * @return void
     */
    public function register_stream_wrapper() {

    }

    /**
     * Does the storage support pre-signed URLs.
     *
     * @return bool.
     */
    public function support_presigned_urls() {
        return false;
    }

    /**
     * Generates pre-signed URL to storage file from its hash.
     *
     * @param string $contenthash File content hash.
     * @param array $headers request headers.
     *
     * @throws \coding_exception
     */
    public function generate_presigned_url($contenthash, $headers = []) {
        throw new \coding_exception("Pre-signed URLs not supported");
    }

    /**
     * Moodle admin settings form to display connection details for the client service.
     *
     * @return string
     * @throws /coding_exception
     */
    public function define_client_check() {
        global $OUTPUT;
        $output = '';
        $connection = $this->test_connection();
        if ($connection->success) {
            $output .= $OUTPUT->notification(get_string('settings:connectionsuccess', 'tool_objectfs'), 'notifysuccess');
            // Check permissions if we can connect.
            $permissions = $this->test_permissions($this->should_test_delete());
            if ($permissions->success) {
                $output .= $OUTPUT->notification(key($permissions->messages), 'notifysuccess');
            } else {
                foreach ($permissions->messages as $message => $type) {
                    $output .= $OUTPUT->notification($message, $type);
                }
            }
        } else {
            $output .= $OUTPUT->notification(
                get_string('settings:connectionfailure', 'tool_objectfs', $connection->details),
                'notifyproblem'
            );
        }
        return $output;
    }

    /**
     * Returns the maximum allowed file size that is to be uploaded.
     *
     * @return int
     */
    public function get_maximum_upload_size() {
        return $this->maxupload;
    }

    /**
     * Proxy range request.
     *
     * @param  \stored_file $file    The file to send
     * @param  object       $ranges  Object with rangefrom, rangeto and length properties.
     * @return false                 If couldn't get data.
     */
    public function proxy_range_request(\stored_file $file, $ranges) {
        return false;
    }

    /**
     * Test proxy range request.
     *
     * @param  object  $filesystem  Filesystem to be tested.
     * @return object
     */
    public function test_range_request($filesystem) {
        return (object)['result' => false, 'error' => ''];
    }

    /**
     * Tests connection to external storage.
     * Override this method in client class.
     *
     * @return object
     */
    public function test_connection() {
        return (object)['success' => false, 'details' => ''];
    }

    /**
     * Tests permissions to external storage.
     * Override this method in client class.
     *
     * @param bool $testdelete Test delete permission and fail the test if could delete object from the storage.
     * @return object
     */
    public function test_permissions($testdelete) {
        return (object)['success' => false, 'details' => ''];
    }

    /**
     * Return expiry time of token, default is -1 meaning not implemented/enabled.
     * @return int
     */
    public function get_token_expiry_time(): int {
        // Returning -1 = not implemented.
        return -1;
    }
}
