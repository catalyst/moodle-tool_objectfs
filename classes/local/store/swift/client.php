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

namespace tool_objectfs\local\store\swift;

use core\check\result;
use tool_objectfs\local\store\swift\stream_wrapper;
use tool_objectfs\local\store\object_client_base;
use tool_objectfs\local\manager;

class client extends object_client_base {

    /** @var string $containername The current container. */
    protected $containername;

    /**
     * The swift client constructor.
     *
     * @param $config
     */
    public function __construct($config) {
        global $CFG;
        $this->autoloader = $CFG->dirroot . '/local/openstack/vendor/autoload.php';

        if ($this->get_availability() && !empty($config)) {
            require_once($this->autoloader);
            $this->maxupload = 5368709120; // 5GiB.
            $this->containername = $config->openstack_container;
            $config->openstack_authtoken = unserialize($config->openstack_authtoken);
            $this->config = $config;
        } else {
            parent::__construct($config);
        }
    }

    private function get_endpoint() {

        $endpoint = [
            'authUrl' => $this->config->openstack_authurl,
            'region'  => $this->config->openstack_region,
            'user'    => [
                'name' => $this->config->openstack_username,
                'password' => $this->config->openstack_password,
                'domain' => ['id' => 'default'],
            ],
            'scope'   => ['project' => ['id' => $this->config->openstack_projectid]]
        ];

        if (!isset($this->config->openstack_authtoken['expires_at'])
            || (new \DateTimeImmutable($this->config->openstack_authtoken['expires_at'])) < ( (new \DateTimeImmutable('now'))->add(new \DateInterval('PT1H')))) {

            $lockfactory = \core\lock\lock_config::get_lock_factory('tool_objectfs_swift');

            // Try and get a lock and do the renewal.
            if ($lock = $lockfactory->get_lock('authtoken', 1)) {

                try {
                    $openstack = new \OpenStack\OpenStack($endpoint);
                    $this->config->openstack_authtoken = $openstack->identityV3()->generateToken($endpoint)->export();
                    manager::set_objectfs_config(['openstack_authtoken' => serialize($this->config->openstack_authtoken)]);
                    $lock->release();
                } catch (\Exception $e) {
                    $lock->release();
                }
            }
        }

        // Use the token if it's valid, otherwise clients will need to use username/password auth.
        if (isset($this->config->openstack_authtoken['expires_at']) && new \DateTimeImmutable($this->config->openstack_authtoken['expires_at']) > new \DateTimeImmutable('now')) {
            $endpoint['cachedToken'] = $this->config->openstack_authtoken;
        }

        return $endpoint;
    }

    public function get_container() {

        if (empty($this->config->openstack_authurl)) {
            throw new \Exception("Invalid authenticaiton URL");
        }

        $params = $this->get_endpoint();
        $openstack = new \OpenStack\OpenStack($params);

        return $openstack->objectStoreV1()->getContainer($this->containername);

    }

    /**
     * Sets the StreamWrapper to allow accessing the remote content via a swift:// path.
     */
    public function register_stream_wrapper() {

        if ($this->get_availability()) {
            static $bootstraped = false;

            if ($bootstraped) {
                return;
            }

            stream_wrapper_register('swift', "tool_objectfs\local\store\swift\stream_wrapper") or die("cant create wrapper");
            \tool_objectfs\local\store\swift\stream_wrapper::set_default_context($this->get_seekable_stream_context());

            $bootstraped = true;

        } else {
            parent::register_stream_wrapper();
        }

    }

    public function get_fullpath_from_hash($contenthash) {
        $filepath = $this->get_filepath_from_hash($contenthash);

        return "swift://$this->containername/$filepath";
    }

    public function get_seekable_stream_context() {

        $this->get_endpoint();
        $context = stream_context_create([
            "swift" => [
                'username' => $this->config->openstack_username,
                'password' => $this->config->openstack_password,
                'projectid' => $this->config->openstack_projectid,
                'tenantname' => $this->config->openstack_tenantname,
                'endpoint' => $this->config->openstack_authurl,
                'region' => $this->config->openstack_region,
                'cachedtoken' => $this->config->openstack_authtoken,
            ]
        ]);
        return $context;
    }


    private function get_md5_from_hash($contenthash) {
        try {

            $key = $this->get_filepath_from_hash($contenthash);

            $obj = $this->get_container()->getObject($key);

            $obj->retrieve();

        } catch (\OpenStack\Common\Error\BadResponseError $e) {
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

    /**
     * Tests connection and returns result.
     * @return result
     */
    public function test_connection(): result {
        try {
            $container = $this->get_container();
        } catch (\Exception $e) {
            return new result(result::ERROR, $e->getMessage());
        }

        try {
            $container->getObject('connection_check_file')->download();
        } catch (\OpenStack\Common\Error\BadResponseError $e) {
            if ($e->getResponse()->getStatusCode() == 404) {
                try {
                    $container->createObject(['name' => 'connection_check_file', 'content' => 'connection_check_file']);
                } catch (\OpenStack\Common\Error\BadResponseError $e) {
                    return new result(result::ERROR, get_string('check:failed', 'tool_objectfs'), $this->get_exception_details($e));
                } catch (\Exception $e) {
                    return new result(result::ERROR, get_string('check:failed', 'tool_objectfs'), $e->getMessage());
                }
            } else {
                $details = get_string('settings:connectionreadfailure', 'tool_objectfs') . $this->get_exception_details($e);
                return new result(result::ERROR, get_string('check:failed', 'tool_objectfs'), $details);
            }
        }

        return new result(result::OK, get_string('check:success', 'tool_objectfs'));
    }

    public function test_permissions($testdelete): result {
        $permissions = new \stdClass();
        $permissions->success = true;
        $permissions->messages = array();

        $container = $this->get_container();

        try {
            $result = $container->createObject(['name' => 'permissions_check_file', 'content' => 'permissions_check_file']);
        } catch (\OpenStack\Common\Error\BadResponseError $e) {
            $details = $this->get_exception_details($e);
            $permissions->messages[get_string('settings:writefailure', 'tool_objectfs') . $details] = 'notifyproblem';
            $permissions->success = false;
        }

        try {
            $result = $container->getObject('permissions_check_file')->download();
        } catch (\OpenStack\Common\Error\BadResponseError $e) {
            $details = $this->get_exception_details($e);
            $permissions->messages[get_string('settings:permissionreadfailure', 'tool_objectfs') . $details] = 'notifyproblem';
            $permissions->success = false;

        }

        try {
            $result = $container->getObject('permissions_check_file')->delete();
            $permissions->messages[get_string('settings:deletesuccess', 'tool_objectfs')] = 'warning';
            $permissions->success = false;
        } catch (\Exception $e) {

            $code = $this->get_error_code($e);

            // Bug in openstack means that a 404 will be returned if an object has not been replicated.
            if ($code !== 404) {
                $permissions->messages[get_string('settings:deleteerror', 'tool_objectfs')] = 'notifyproblem';
                $permissions->success = false;
            }
        }

        if ($permissions->success) {
            $permissions->messages[get_string('settings:permissioncheckpassed', 'tool_objectfs')] = 'notifysuccess';
        }

        $status = $permissions->success ? result::OK : result::ERROR;
        $summarystr = result::OK ? 'check:passed' : 'check:failed';
        $summary = get_string($summarystr, 'tool_objectfs');
        $details = implode("\n", $permissions->messages);

        return new result($status, $summary, $details);
    }

    protected function get_exception_details(\OpenStack\Common\Error\BadResponseError $e) {

        $message = $e->getResponse()->getReasonPhrase();
        $details = ' ';

        if ($message) {
            $details .= "ERROR MSG: " . $message . "\n";
        }

        return $details;
    }

    /**
     * swift settings form with the following elements:
     *
     * @param admin_settingpage $settings
     * @param $config
     * @return admin_settingpage
     */
    public function define_client_section($settings, $config) {

        $settings->add(new \admin_setting_heading('tool_objectfs/openstack',
            new \lang_string('settings:openstack:header', 'tool_objectfs'), $this->define_client_check()));

        $settings->add(new \admin_setting_configtext('tool_objectfs/openstack_authurl',
            new \lang_string('settings:openstack:authurl', 'tool_objectfs'),
            new \lang_string('settings:openstack:authurl_help', 'tool_objectfs'), ''));

        $settings->add(new \admin_setting_configtext('tool_objectfs/openstack_region',
            new \lang_string('settings:openstack:region', 'tool_objectfs'),
            new \lang_string('settings:openstack:region_help', 'tool_objectfs'), ''));

        $settings->add(new \admin_setting_configtext('tool_objectfs/openstack_container',
            new \lang_string('settings:openstack:container', 'tool_objectfs'),
            new \lang_string('settings:openstack:container_help', 'tool_objectfs'), ''));

        $settings->add(new \admin_setting_configtext('tool_objectfs/openstack_username',
            new \lang_string('settings:openstack:username', 'tool_objectfs'),
            new \lang_string('settings:openstack:username_help', 'tool_objectfs'), ''));

        $settings->add(new \admin_setting_configpasswordunmask('tool_objectfs/openstack_password',
            new \lang_string('settings:openstack:password', 'tool_objectfs'),
            new \lang_string('settings:openstack:password', 'tool_objectfs'), ''));

        $settings->add(new \admin_setting_configtext('tool_objectfs/openstack_tenantname',
            new \lang_string('settings:openstack:tenantname', 'tool_objectfs'),
            new \lang_string('settings:openstack:tenantname_help', 'tool_objectfs'), ''));

        $settings->add(new \admin_setting_configtext('tool_objectfs/openstack_projectid',
            new \lang_string('settings:openstack:projectid', 'tool_objectfs'),
            new \lang_string('settings:openstack:projectid_help', 'tool_objectfs'), ''));

        return $settings;
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

    /**
     * Deletes a file (object) within openstack swift storage.
     *
     * @param string $fullpath full path to file to be deleted
     */
    public function delete_file($fullpath) {
        unlink($fullpath);
    }

    /**
     * Moves file (object) within openstack swift storage.
     *
     * @param string $currentpath     current path to file to be moved.
     * @param string $destinationpath destination path to file.
     */
    public function rename_file($currentpath, $destinationpath) {
        rename($currentpath, $destinationpath);
    }

    /**
     * Returns relative path to blob from fullpath to use with php file functions.
     *
     * @param string $fullpath full path to azure blob.
     * @return   string    relative path to azure blob.
     * @throws \Exception if fails
     */
    public function get_relative_path_from_fullpath($fullpath) {
        $container = $this->get_container();
        return str_replace("swift://$container->name/", '', $fullpath);
    }
}
