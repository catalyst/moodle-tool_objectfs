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
 * Openstack Swift client
 *
 * @package    tool_objectfs
 * @copyright  2017 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace tool_objectfs\client;

defined('MOODLE_INTERNAL') || die();

$autoloader = $CFG->dirroot . '/local/openstack/vendor/autoload.php';

if (!file_exists($autoloader)) {

    // Stub class with bare implementation for when the SDK prerequisite does not exist.
    class swift_client {
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

class swift_client implements object_client {

    /** @var string $containername The current container. */
    protected $containername;

    protected $maxbytes = 5368709120; // 5GiB.

    protected $config;

    /**
     * The swift_client constructor.
     *
     * @param $config
     */
    public function __construct($config) {
        global $CFG;

        if (empty($config)) {
            return;
        }

        $this->containername = $config->openstack_container;
        $this->config = $config;
    }


    public function get_container() {

        static $token;

        if (empty($this->config->openstack_authurl)) {
            throw new \Exception("Invalid authenticaiton URL");
        }

        $params = [
            'authUrl' => $this->config->openstack_authurl,
            'region'  => $this->config->openstack_region,
            'user'    => [
                'name' => $this->config->openstack_username,
                'password' => $this->config->openstack_password,
                'domain' => ['id' => 'default'],
            ],
            'scope'   => ['project' => ['id' => $this->config->openstack_projectid]]
        ];

        if (!isset($token['expires_at']) || (new \DateTimeImmutable($token['expires_at'])) < (new \DateTimeImmutable('now'))) {
            $openstack = new \OpenStack\OpenStack($params);
            $token = $openstack->identityV3()->generateToken($params)->export();
        } else {
            $params['cachedToken'] = $token;
            $openstack = new \OpenStack\OpenStack($params);
        }

        return $openstack->objectStoreV1()->getContainer($this->containername);

    }

    /**
     * Returns true if the Openstack SDK exists and has been loaded.
     *
     * @return bool
     */
    public function get_availability() {
        return true;
    }

    /**
     * Returns the maximum allowed file size that is to be uploaded.
     *
     * @return int
     */
    public function get_maximum_upload_size() {
        return $this->maxbytes;
    }


    /**
     * Sets the StreamWrapper to allow accessing the remote content via a swift:// path.
     */
    public function register_stream_wrapper() {

        static $bootstraped = false;

        if ($bootstraped) {
            return;
        }

        stream_wrapper_register('swift', "tool_objectfs\swift\streamwrapper") or die("cant create wrapper");
        \tool_objectfs\swift\streamwrapper::set_default_context($this->get_seekable_stream_context());

        $bootstraped = true;
    }

    public function get_fullpath_from_hash($contenthash) {
        $filepath = $this->get_filepath_from_hash($contenthash);

        return "swift://$this->containername/$filepath";
    }

    public function get_seekable_stream_context() {
        $context = stream_context_create([
            "swift" => [
                'username' => $this->config->openstack_username,
                'password' => $this->config->openstack_password,
                'projectid' => $this->config->openstack_projectid,
                'tenantname' => $this->config->openstack_tenantname,
                'endpoint' => $this->config->openstack_authurl,
                'region' => $this->config->openstack_region,
            ]
        ]);
        return $context;
    }


    private function get_md5_from_hash($contenthash) {
        try {

            $key = $this->get_filepath_from_hash($contenthash);

            $obj = $this->get_container()->getObject($key);

            $obj->retrieve();

        } catch (BadResponseError $e) {
            return false;
        }

        return $obj->hash;
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

    protected function get_filepath_from_hash($contenthash) {
        $l1 = $contenthash[0] . $contenthash[1];
        $l2 = $contenthash[2] . $contenthash[3];

        return "$l1/$l2/$contenthash";
    }


    public function test_connection() {

        $connection = new \stdClass();
        $connection->success = true;
        $connection->message = '';

        try {
            $container = $this->get_container();
        } catch (\Exception $e) {
            $connection->success = false;
            $connection->message = $e->getMessage();
            return $connection;
        }

        try {
            $result = $container->createObject(['name' => 'connection_check_file', 'content' => 'connection_check_file']);
            $connection->message = get_string('settings:connectionsuccess', 'tool_objectfs');
        } catch (BadResponseError $e) {
            $connection->success = false;
            $details = $this->get_exception_details($e);
            $connection->message = get_string('settings:connectionfailure', 'tool_objectfs') . $details;
        } catch (Exception $e) {
            $connection->success = false;
            $connection->message = get_string('settings:connectionfailure', 'tool_objectfs');
        }

        return $connection;
    }

    public function test_permissions() {
        $permissions = new \stdClass();
        $permissions->success = true;
        $permissions->messages = array();

        $container = $this->get_container();

        try {
            $result = $container->createObject(['name' => 'permissions_check_file', 'content' => 'permissions_check_file']);
        } catch (BadResponseError $e) {
            $details = $this->get_exception_details($e);
            $permissions->messages[] = get_string('settings:writefailure', 'tool_objectfs') . $details;
            $permissions->success = false;
        }

        try {
            $result = $container->getObject('permissions_check_file')->download();
        } catch (BadResponseError $e) {
            $details = $this->get_exception_details($e);
            $permissions->messages[] = get_string('settings:readfailure', 'tool_objectfs') . $details;
            $permissions->success = false;

        }

        try {
            $result = $container->getObject('permissions_check_file')->delete();
        } catch (\Exception $e) {

            $code = $this->get_error_code($e);

            // Bug in openstack means that a 404 will be returned if an object has not been replicated.
            if ($code !== 404) {
                $permissions->success = false;
                $permissions->messages[] = get_string('settings:deleteerror', 'tool_objectfs');
            }
        }

        if ($permissions->success) {
            $permissions->messages[] = get_string('settings:permissioncheckpassed', 'tool_objectfs');
        }

        return $permissions;
    }

    protected function get_exception_details(BadResponseError $e) {

        $message = $e->getResponse()->getReasonPhrase();
        $details = ' ';

        if ($message) {
            $details .= "ERROR MSG: " . $message . "\n";
        }

        return $details;
    }

    /**
     * Moodle form element to display connection details for the swift service.
     *
     * @param $mform
     * @param $config
     * @return mixed
     */
    public function define_swift_check($mform, $config) {
        global $OUTPUT;

        $client = new swift_client($config, false);
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
            $permissions = true;
        }
        return $mform;
    }

    /**
     * swift settings form with the following elements:
     *
     * @param $mform
     * @param $config
     * @return mixed
     */
    public function define_client_section($mform, $config) {

        $mform->addElement('header', 'openstackheader', get_string('settings:openstack:header', 'tool_objectfs'));
        $mform->setExpanded('openstackheader');

        $mform = $this->define_swift_check($mform, $config);

        $mform->addElement('text', 'openstack_username', get_string('settings:openstack:username', 'tool_objectfs'));
        $mform->addHelpButton('openstack_username', 'settings:openstack:username', 'tool_objectfs');
        $mform->setType("openstack_username", PARAM_TEXT);

        $mform->addElement('password', 'openstack_password', get_string('settings:openstack:password', 'tool_objectfs'));
        $mform->addHelpButton('openstack_password', 'settings:openstack:password', 'tool_objectfs');
        $mform->setType("openstack_password", PARAM_TEXT);

        $mform->addElement('text', 'openstack_authurl', get_string('settings:openstack:authurl', 'tool_objectfs'));
        $mform->addHelpButton('openstack_authurl', 'settings:openstack:authurl', 'tool_objectfs');
        $mform->setType("openstack_authurl", PARAM_URL);

        $mform->addElement('text', 'openstack_region', get_string('settings:openstack:region', 'tool_objectfs'));
        $mform->addHelpButton('openstack_region', 'settings:openstack:region', 'tool_objectfs');
        $mform->setType("openstack_region", PARAM_TEXT);

        $mform->addElement('text', 'openstack_tenantname', get_string('settings:openstack:tenantname', 'tool_objectfs'));
        $mform->addHelpButton('openstack_tenantname', 'settings:openstack:tenantname', 'tool_objectfs');
        $mform->setType("openstack_tenantname", PARAM_TEXT);

        $mform->addElement('text', 'openstack_projectid', get_string('settings:openstack:projectid', 'tool_objectfs'));
        $mform->addHelpButton('openstack_projectid', 'settings:openstack:projectid', 'tool_objectfs');
        $mform->setType("openstack_projectid", PARAM_TEXT);

        $mform->addElement('text', 'openstack_container', get_string('settings:openstack:container', 'tool_objectfs'));
        $mform->addHelpButton('openstack_container', 'settings:openstack:container', 'tool_objectfs');
        $mform->setType("openstack_container", PARAM_TEXT);

        return $mform;
    }


    /**
     * Return the error code
     *
     * @param $e The exception that contains the XML body.
     * @return int The error code.
     */
    private function get_error_code($e) {

        return $e->getResponse()->getStatusCode();
    }

}
