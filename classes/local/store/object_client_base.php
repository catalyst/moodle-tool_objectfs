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

use core\check\result;

abstract class object_client_base implements object_client {

    protected $autoloader;
    protected $expirationtime;
    protected $testdelete = true;
    public $presignedminfilesize;
    public $enablepresignedurls;

    /** @var int $maxupload Maximum allowed file size that can be uploaded. */
    protected $maxupload;

    /** @var object $config Client config. */
    protected $config;

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
    public function generate_presigned_url($contenthash, $headers = array()) {
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

        // TODO use MDL check api admin setting if available ???

        $checks = tool_objectfs_status_checks();

        $output = '';

        foreach ($checks as $check) {
            // This is fixed in 4.4 by MDL-67898.
            // But until that is more common, the component must be set manually,
            // as the default is incorrect when viewed in the admin tree.
            $check->set_component('tool_objectfs');

            $result = $check->get_result();

            $status = $result->get_status();

            // If status was N/A - ignore it.
            if ($status == result::NA) {
                continue;
            }

            $str = $check->get_name() . ": " . $result->get_summary();

            // Only include details if failure.
            if ($status != result::OK) {
                $str .= "\n" . $result->get_details();
            }

            $type = $status == result::OK ? 'notifysuccess' : 'notifyerror';

            $output .= $OUTPUT->notification(nl2br($str), $type);
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
     * @return result
     */
    public function test_range_request($filesystem): result {
        return new result(result::INFO, get_string('check:notimplemented', 'tool_objectfs'));
    }

    /**
     * Tests connection to external storage.
     * Override this method in client class.
     *
     * @return result
     */
    public function test_connection(): result {
        return new result(result::INFO, get_string('check:notimplemented', 'tool_objectfs'));
    }

    /**
     * Tests permissions to external storage.
     * Override this method in client class.
     *
     * @param bool $testdelete Test delete permission and fail the test if could delete object from the storage.
     * @return object
     */
    public function test_permissions($testdelete): result {
        return new result(result::INFO, get_string('check:notimplemented', 'tool_objectfs'));
    }

    /**
     * Tests configuration is OK.
     * Override this method in client class.
     *
     * @return result
     */
    public function test_configuration(): result {
        return new result(result::INFO, get_string('check:notimplemented', 'tool_objectfs'));
    }
}
