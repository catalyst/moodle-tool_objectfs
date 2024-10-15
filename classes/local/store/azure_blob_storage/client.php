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

namespace tool_objectfs\local\store\azure_blob_storage;

use admin_settingpage;
use coding_exception;
use GuzzleHttp\Psr7\Utils;
use tool_objectfs\local\store\object_client_base;
use local_azureblobstorage\api;
use local_azureblobstorage\stream_wrapper;
use stdClass;
use Throwable;

/**
 * Azure blob storage client
 *
 * @package    tool_objectfs
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @copyright  2024 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class client extends object_client_base {

    /** @var api $api Azure API */
    protected api $api;

    /**
     * Creates object client
     * @param stdClass $config / TODO is this maybe null ?
     */
    public function __construct($config) {
        if (empty($config) || !$this->get_availability()) {
            parent::__construct($config);
            return;
        }

        $this->api = new api($config->azure_accountname, $config->azure_container, $config->azure_sastoken);
        $this->config = $config;
        $this->maxupload = api::MAX_BLOCK_SIZE;
    }

    /**
     * Determines if this filesystem is available for use.
     * @return bool
     */
    public function get_availability(): bool {
        // Requires local_azureblobstorage to be installed.
        // Namespace changed in 4.4+.
        if (class_exists('\core\plugin_manager')) {
            $info = \core\plugin_manager::instance()->get_plugin_info('local_azureblobstorage');
            // For 4.2 and 4.3.
        } else if (class_exists('\core_plugin_manager')) {
            $info = \core_plugin_manager::instance()->get_plugin_info('local_azureblobstorage');
        } else {
            throw new coding_exception("Could not load plugin manager class");
        }

        // Info is empty if plugin is not installed or no API is setup (missing config?).
        return !empty($info);
    }

    /**
     * Sets the StreamWrapper to allow accessing the remote content via a blob:// path.
     */
    public function register_stream_wrapper() {
        if ($this->get_availability()) {
            stream_wrapper::register($this->api);
        } else {
            parent::register_stream_wrapper();
        }
    }

    /**
     * Returns the full path for a given file by contenthash
     * @param string $contenthash
     * @return string filepath
     */
    public function get_fullpath_from_hash($contenthash): string {
        $filepath = $this->get_filepath_from_hash($contenthash);
        return "blob://$filepath";
    }

    /**
     * Returns the filepath from the contenthash, mimicking the
     * structure of the filedir storage system.
     * @param string $contenthash
     * @return string filepath
     */
    protected function get_filepath_from_hash($contenthash): string {
        $l1 = $contenthash[0] . $contenthash[1];
        $l2 = $contenthash[2] . $contenthash[3];
        return "$l1/$l2/$contenthash";
    }

    /**
     * Returns the blob key (the key used to reference the blob) from a given filepath.
     * @param string $filepath
     * @return string
     */
    protected function get_blob_key_from_path(string $filepath): string {
        return str_replace("blob://", '', $filepath);
    }

    /**
     * Deletes a given file
     * @param string $fullpath
     */
    public function delete_file($fullpath) {
        // Stream wrapper supports unlinking, so just unlink.
        unlink($fullpath);
    }

    /**
     * Renames a given file
     * @param string $currentpath
     * @param string $destinationpath
     */
    public function rename_file($currentpath, $destinationpath) {
        // Azure does not support renaming, instead the file is copied
        // and the old one is deleted.
        copy($currentpath, $destinationpath);
        $this->delete_file($currentpath);
    }

    /**
     * Verifies an object is uploaded correctly.
     * In Azure, this is done by checking the md5 hash of the contents.
     * @param string $contenthash
     * @param string $localpath
     * @return bool
     */
    public function verify_object($contenthash, $localpath) {
        // If the object is uploaded to Azure the content will always be correct,
        // because Azure will reject the original upload request if the md5 given during
        // upload does not match.
        // So here we just check the blob exists, and don't actually care about comparing the md5.
        try {
            // Just query the properties to confirm the file does indeed exist.
            $key = $this->get_filepath_from_hash($contenthash);
            $this->api->get_blob_properties_async($key)->wait();
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Returns a stream context used to handle file IO
     * @return resource stream resource
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
     * Test permissions by uploading and doing various actions.
     * @param bool $testdelete if should test deletion.
     * @return stdClass containing 'success' and 'messages' values.
     */
    public function test_permissions($testdelete): stdClass {
        $key = 'permissions_check_test';
        $file = Utils::streamFor('test permission file');
        $filemd5 = hex2bin(md5('test permission file'));

        // Try create a file.
        try {
            $this->api->put_blob_async($key, $file, $filemd5)->wait();
        } catch (Throwable $e) {
            return (object) [
                'success' => false,
                'messages' => [get_string('settings:writefailure', 'tool_objectfs') . $e->getMessage() => 'notifyproblem'],
            ];
        }

        // Try read the file that was created.
        try {
            $this->api->get_blob_async($key, $file, $filemd5)->wait();
        } catch (Throwable $e) {
            return (object) [
                'success' => false,
                'messages' => [get_string('settings:permissionreadfailure', 'tool_objectfs') . $e->getMessage() => 'notifyproblem'],
            ];
        }

        // If testing delete, try delete the test file.
        if ($testdelete) {
            try {
                $this->api->delete_blob_async($key)->wait();
            } catch (Throwable $e) {
                return (object) [
                    'success' => false,
                    'messages' => [get_string('settings:deleteerror', 'tool_objectfs') . $e->getMessage() => 'notifyproblem'],
                ];
            }
        }

        return (object) [
            'success' => true,
            'messages' => [get_string('settings:permissioncheckpassed', 'tool_objectfs') => 'notifysuccess'],
        ];
    }

    /**
     * Tests connection
     * @return stdClass with 'success' and 'details' values.
     */
    public function test_connection(): stdClass {
        // Try to create a file.
        try {
            $this->api->put_blob_async('connection_check_test', Utils::streamFor('test contents'), hex2bin(md5('test contents')));
        } catch (Throwable $e) {
            return (object) [
                'success' => false,
                'details' => $e->getMessage(),
            ];
        }

        return (object) [
            'success' => true,
            'details' => '',
        ];
    }

    /**
     * Returns token expiry time
     * @return int
     */
    public function get_token_expiry_time(): int {
        if (empty($this->config->azure_sastoken)) {
            return -1;
        }

        // Parse the sas token (it just uses url parameter encoding).
        $parts = [];
        parse_str($this->config->azure_sastoken, $parts);

        // Get the 'se' part (signed expiry).
        if (!isset($parts['se'])) {
            // Assume expired (malformed).
            return 0;
        }

        // Parse timestamp string into unix timestamp int.
        $expirystr = $parts['se'];
        return strtotime($expirystr);
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
    public function define_client_section($settings, $config): admin_settingpage {
        $settings->add(new \admin_setting_heading('tool_objectfs/azure',
            new \lang_string('settings:azure:header', 'tool_objectfs'), $this->define_client_check()));

        $settings->add(new \admin_setting_configtext('tool_objectfs/azure_accountname',
            new \lang_string('settings:azure:accountname', 'tool_objectfs'),
            new \lang_string('settings:azure:accountname_help', 'tool_objectfs'), ''));

        $settings->add(new \admin_setting_configtext('tool_objectfs/azure_container',
            new \lang_string('settings:azure:container', 'tool_objectfs'),
            new \lang_string('settings:azure:container_help', 'tool_objectfs'), ''));

        $settings->add(new \admin_setting_configpasswordunmask('tool_objectfs/azure_sastoken',
            new \lang_string('settings:azure:sastoken', 'tool_objectfs'),
            new \lang_string('settings:azure:sastoken_help', 'tool_objectfs'), ''));

        return $settings;
    }
}
