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

defined('MOODLE_INTERNAL') || die();

abstract class object_client_base implements object_client {
    const MAX_UPLOAD = OBJECTFS_BYTES_IN_TERABYTE * 5;

    protected $autoloader;
    protected $expirationtime;
    protected $testdelete = true;
    public $presignedminfilesize;
    public $enablepresignedurls;

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
    public function generate_presigned_url($contenthash, $headers) {
        throw new \coding_exception("Pre-signed URLs not supported");
    }

    /**
     * Returns given header from headers set.
     *
     * @param array $headers request headers.
     * @param string $search
     *
     * @return string header.
     */
    public function get_header($headers, $search) {
        foreach ($headers as $header) {
            $found = strpos($header, $search);
            if ($found !== false) {
                return substr($header, strlen($search) + 1);
            }
        }
        return '';
    }

    /**
     * Moodle admin settings form to display connection details for the client service.
     *
     * @param $client
     */
    public function define_client_check($client) {
        if (CLI_SCRIPT) {
            // Running from CLI and there is no session.
            return;
        }
        $connection = $client->test_connection();
        if ($connection->success) {
            $this->notification($connection->message);
            // Check permissions if we can connect.
            $permissions = $client->test_permissions($this->testdelete);
            if ($permissions->success) {
                $message = key($permissions->messages) . ' ' . current($permissions->messages);
                $this->notification($message);
            } else {
                foreach ($permissions->messages as $message => $type) {
                    $this->notification($message . ' - ' . $type, 'warning');
                }
            }
        } else {
            $this->notification($connection->message, 'error');
        }
    }

    /**
     * Class \core\notification is only available for moodle versions > 3.1
     * @param $message
     * @param string $type
     */
    public function notification($message, $type = 'success') {
        global $CFG, $OUTPUT, $SESSION;;
        $methodsmap = [
            'success' => 'notifysuccess',
            'warning' => 'notifyproblem',
            'error' => 'notifyproblem',
        ];
        if (!empty($CFG->branch) && $CFG->branch < '31' && isset($methodsmap[$type])) {
            $type = $methodsmap[$type];
            echo $OUTPUT->notification($message, $type);
        } else {
            $SESSION->notifications = [];
            call_user_func('\core\notification::' . $type, $message);
        }
    }
}
