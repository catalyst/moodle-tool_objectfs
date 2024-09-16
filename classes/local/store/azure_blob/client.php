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

namespace tool_objectfs\local\store\azure_blob;

use core\lang_string;
use GuzzleHttp\Psr7\Utils;
use local_azureblobstorage\api;
use stdClass;
use tool_objectfs\local\store\object_client_base;

/**
 * Azure Blob Storage client.
 *
 * @package    tool_objectfs
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class client extends object_client_base {

    /** @var Api $client Azure rest API interface. */
    protected $client;

    /** @var string Container name, used for constructing paths */
    protected $container;

    /**
     * The azure client constructor.
     *
     * @param \stdclass $config
     */
    public function __construct($config) {
        if ($this->get_availability() && !empty($config)) {
            $this->client = new api($config->azure_accountname, $config->azure_container, $this->clean_sastoken($config->azure_sastoken));
            $this->container = $config->azure_container;
        } else {
            parent::__construct($config);
        }
    }

    /**
     * Sets the StreamWrapper to allow accessing the remote content via a blob:// path.
     */
    public function register_stream_wrapper() {
        if ($this->get_availability()) {
            stream_wrapper::register($this->client);
        } else {
            parent::register_stream_wrapper();
        }
    }

    /**
     * Returns azure fullpath to use with php file functions.
     *
     * @param  string $contenthash contenthash used as key in azure.
     * @return string fullpath to azure object.
     */
    public function get_fullpath_from_hash($contenthash) {
        $filepath = $this->get_filepath_from_hash($contenthash);
        return "blob://$this->container/$filepath";
    }

    /**
     * Deletes file (blob) in azure blob storage.
     *
     * @param string $fullpath path to azure blob.
     */
    public function delete_file($fullpath) {
        // TODO get hash from fullpath
        // TODo call $client->delete_blob($hash)
    }

    /**
     * Moves file (blob) within azure blob storage.
     *
     * @param string $currentpath     current path to file to be moved.
     * @param string $destinationpath destination path to file.
     */
    public function rename_file($currentpath, $destinationpath) {
        copy($currentpath, $destinationpath);

        $this->delete_file($currentpath);
    }

    /**
     * Returns relative path to blob from fullpath to use with php file functions.
     *
     * @param    string    $fullpath full path to azure blob.
     * @return   string    relative path to azure blob.
     */
    public function get_relative_path_from_fullpath($fullpath) {
        $relativepath = str_replace("blob://$this->container/", '', $fullpath);

        return $relativepath;
    }

    /**
     * Returns a context for the stream that is seekable.
     *
     * @return resource
     */
    public function get_seekable_stream_context() {
        $context = stream_context_create([
            'blob' => [
                'seekable' => true,
            ],
        ]);
        return $context;
    }

    /**
     * TODO this might not be necessary anymore ?
     *
     * Trim a leading '?' character from the sas token.
     *
     * @param string $sastoken
     * @return bool|string
     */
    private function clean_sastoken($sastoken) {
        if (substr($sastoken, 0, 1) === '?') {
            $sastoken = substr($sastoken, 1);
        }

        return $sastoken;
    }

    /**
     * Gets the md5 for a file that is currently stored in Azure.
     * Generally this is used for upload verification.
     *
     * @param string $contenthash
     * @return string MD5 hash
     */
    private function get_md5_from_hash($contenthash) {
        try {
            $key = $this->get_filepath_from_hash($contenthash);

            $result = $this->client->get_blob_properties($this->container, $key)->wait();

        // TODO catch different exception ?
        } catch (\MicrosoftAzure\Storage\Common\Exceptions\ServiceException $e) {
            return false;
        }

        // TODO get the proper header name.
        $contentmd5 = $result['x-ms-contentmd5'];

        // TODO should we leave as base64 or decode it ??
        if ($contentmd5) {
            $md5 = bin2hex(base64_decode($contentmd5));
        } else {
            $md5 = trim($result->getETag(), '"'); // Strip quotation marks.
        }

        return $md5;
    }

    /**
     * Verifies the object using the md5 stored in moodle vs blob storage.
     *
     * @param string $contenthash
     * @param string $localpath
     *
     * @return bool
     */
    public function verify_object($contenthash, $localpath) {
        $localmd5 = ''; // TODO.
        $remotemd5 = $this->get_md5_from_hash($contenthash);
        return $localmd5 == $remotemd5;
    }

    /**
     * Return filepath from the content hash.
     *
     * @param string $contenthash
     *
     * @return string
     */
    protected function get_filepath_from_hash($contenthash) {
        $l1 = $contenthash[0] . $contenthash[1];
        $l2 = $contenthash[2] . $contenthash[3];
        return "$l1/$l2/$contenthash";
    }

    /**
     * Tests connection by trying to create a test blob.
     *
     * @return stdClass
     */
    public function test_connection() {
        $connection = new \stdClass();
        $connection->success = true;
        $connection->details = '';

        try {
            $md5 = hex2bin(md5('connection_check_file'));
            $this->client->put_blob('connection_check_file', Utils::streamFor('connection_check_file'), $md5);
        
        // TODO catch different exceptions
        } catch (\MicrosoftAzure\Storage\Common\Exceptions\ServiceException $e) {
            $connection->success = false;
            $connection->details = $this->get_exception_details($e);
        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            $connection->success = false;
            $connection->details = $e->getMessage();
        }

        return $connection;
    }

    /**
     * TEsts permissions by trying to create, get and delete a blob.
     * @param mixed $testdelete
     *
     * @return stdClass
     */
    public function test_permissions($testdelete) {
        // TODO redo this and make it neat and tidy.
        // TODO also support when objectfs deletion is disabled, don't check deletion.

        $permissions = new \stdClass();
        $permissions->success = true;
        $permissions->messages = [];

        // try {
        //     $result = $this->client->createBlockBlob($this->container, 'permissions_check_file', 'permissions_check_file');
        // } catch (\MicrosoftAzure\Storage\Common\Exceptions\ServiceException $e) {
        //     $details = $this->get_exception_details($e);
        //     $permissions->messages[get_string('settings:writefailure', 'tool_objectfs') . $details] = 'notifyproblem';
        //     $permissions->success = false;
        // }

        // try {
        //     $result = $this->client->getBlob($this->container, 'permissions_check_file');
        // } catch (\MicrosoftAzure\Storage\Common\Exceptions\ServiceException $e) {
        //     $errorcode = $this->get_body_error_code($e);

        //     // Write could have failed.
        //     if ($errorcode !== 'BlobNotFound') {
        //         $details = $this->get_exception_details($e);
        //         $permissions->messages[get_string('settings:permissionreadfailure', 'tool_objectfs') . $details] = 'notifyproblem';
        //         $permissions->success = false;
        //     }
        // }

        // try {
        //     $result = $this->client->deleteBlob($this->container, 'permissions_check_file');
        //     $permissions->messages[get_string('settings:deletesuccess', 'tool_objectfs')] = 'warning';
        //     $permissions->success = false;
        // } catch (\MicrosoftAzure\Storage\Common\Exceptions\ServiceException $e) {
        //     $errorcode = $this->get_body_error_code($e);

        //     // Something else went wrong.
        //     if ($errorcode !== 'AuthorizationPermissionMismatch') {
        //         $details = $this->get_exception_details($e);
        //         $permissions->messages[get_string('settings:deleteerror', 'tool_objectfs') . $details] = 'notifyproblem';
        //         $permissions->success = false;
        //     }
        // }

        // if ($permissions->success) {
        //     $permissions->messages[get_string('settings:permissioncheckpassed', 'tool_objectfs')] = 'notifysuccess';
        // }

        return $permissions;
    }

    /**
     * Azure settings form with the following elements:
     *
     * Storage account name.
     * Container name.
     * Shared Access Signature.
     *
     * @param admin_settingpage $settings
     * @param  \stdClass $config
     * @return admin_settingpage
     */
    public function define_client_section($settings, $config) {

        $settings->add(new \admin_setting_heading('tool_objectfs/azure',
            new lang_string('settings:azure:header', 'tool_objectfs'), $this->define_client_check()));

        $settings->add(new \admin_setting_configtext('tool_objectfs/azure_accountname',
            new lang_string('settings:azure:accountname', 'tool_objectfs'),
            new lang_string('settings:azure:accountname_help', 'tool_objectfs'), ''));

        $settings->add(new \admin_setting_configtext('tool_objectfs/azure_container',
            new lang_string('settings:azure:container', 'tool_objectfs'),
            new lang_string('settings:azure:container_help', 'tool_objectfs'), ''));

        $settings->add(new \admin_setting_configpasswordunmask('tool_objectfs/azure_sastoken',
            new lang_string('settings:azure:sastoken', 'tool_objectfs'),
            new lang_string('settings:azure:sastoken_help', 'tool_objectfs'), ''));

        return $settings;
    }
}
