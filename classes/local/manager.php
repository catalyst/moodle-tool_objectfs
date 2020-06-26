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
 * manager class.
 *
 * @package   tool_objectfs
 * @author    Gleimer Mora <gleimermora@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\local;

use stdClass;
use tool_objectfs\local\store\object_file_system;

defined('MOODLE_INTERNAL') || die();

class manager {

    /**
     * @param $config
     */
    public static function set_objectfs_config($config) {
        foreach ($config as $key => $value) {
            set_config($key, $value, 'tool_objectfs');
        }
    }

    /**
     * @return stdClass
     * @throws \dml_exception
     */
    public static function get_objectfs_config() {
        global $CFG;
        $config = new stdClass;
        $config->enabletasks = 0;
        $config->enablelogging = 0;
        $config->sizethreshold = 1024 * 10;
        $config->minimumage = 7 * DAYSECS;
        $config->deletelocal = 0;
        $config->consistencydelay = 10 * MINSECS;
        $config->maxtaskruntime = MINSECS;
        $config->logging = 0;
        $config->preferexternal = 0;
        $config->batchsize = 10000;
        $config->useproxy = 0;

        $config->filesystem = '';
        $config->enablepresignedurls = 0;
        $config->expirationtime = 2 * HOURSECS;
        $config->presignedminfilesize = 0;
        $config->proxyrangerequests = 0;

        // S3 file system.
        $config->s3_usesdkcreds = 0;
        $config->s3_key = '';
        $config->s3_secret = '';
        $config->s3_bucket = '';
        $config->s3_region = 'us-east-1';
        $config->s3_base_url = '';

        // Digital ocean file system.
        $config->do_key = '';
        $config->do_secret = '';
        $config->do_space = '';
        $config->do_region = 'sfo2';

        // Azure file system.
        $config->azure_accountname = '';
        $config->azure_container = '';
        $config->azure_sastoken = '';

        // Swift(OpenStack) file system.
        $config->openstack_authurl = '';
        $config->openstack_region = '';
        $config->openstack_container = '';
        $config->openstack_username = '';
        $config->openstack_password = '';
        $config->openstack_tenantname = '';
        $config->openstack_projectid = '';

        // Cloudfront CDN with Signed URLS - canned policy.
        $config->cloudfrontresourcedomain = '';
        $config->cloudfrontkeypairid = '';

        // SigningMethod - determine whether S3 or Cloudfront etc should be used.
        $config->signingmethod = '';  // This will be the default if not otherwise set. Values ('s3' | 'cf').

        $storedconfig = get_config('tool_objectfs');

        // Override defaults if set.
        foreach ($storedconfig as $key => $value) {
            $config->$key = $value;
        }
        return $config;
    }

    /**
     * @param $config
     * @return bool
     */
    public static function get_client($config) {
        $clientclass = self::get_client_classname_from_fs($config->filesystem);

        if (class_exists($clientclass)) {
            return new $clientclass($config);
        }

        return false;
    }

    /**
     * @param $contenthash
     * @param $newlocation
     * @return mixed|stdClass
     * @throws \dml_exception
     */
    public static function update_object_by_hash($contenthash, $newlocation) {
        global $DB;
        $newobject = new stdClass();
        $newobject->contenthash = $contenthash;

        $oldobject = $DB->get_record('tool_objectfs_objects', ['contenthash' => $contenthash]);
        if ($oldobject) {
            $newobject->timeduplicated = $oldobject->timeduplicated;
            $newobject->id = $oldobject->id;

            // If location hasn't changed we do not need to update.
            if ((int)$oldobject->location === $newlocation) {
                return $oldobject;
            }

            return self::update_object($newobject, $newlocation);
        }
        $newobject->timeduplicated = time();
        $newobject->location = $newlocation;
        $DB->insert_record('tool_objectfs_objects', $newobject);

        return $newobject;
    }

    /**
     * @param stdClass $object
     * @param $newlocation
     * @return stdClass
     * @throws \dml_exception
     */
    public static function update_object(stdClass $object, $newlocation) {
        global $DB;

        // If location change is 'duplicated' we update timeduplicated.
        if ($newlocation === OBJECT_LOCATION_DUPLICATED) {
            $object->timeduplicated = time();
        }

        $object->location = $newlocation;
        $DB->update_record('tool_objectfs_objects', $object);

        return $object;
    }

    /**
     * @return string
     * @throws \coding_exception
     * @throws \dml_exception
     */
    static public function cloudfront_pem_exists() {
        global $OUTPUT;
        $config = self::get_objectfs_config();
        if ('cf' !== $config->signingmethod) {
            return '';
        }
        if (empty($config->cloudfrontprivatekey)) {
            return '';
        }
        $path = $config->cloudfrontprivatekey;
        $text = 'settings:presignedcloudfronturl:cloudfront_pem_found';
        $type = 'notifysuccess';
        if (false === self::parse_cloudfront_private_key($path)) {
            $text = 'settings:presignedcloudfronturl:cloudfront_pem_not_found';
            $type = 'notifyproblem';
        }
        return $OUTPUT->notification(get_string($text, OBJECTFS_PLUGIN_NAME), $type);
    }

    /**
     * Returns a private key resource needed generate a valid cloudfront signature,
     * it can be the string content of the .pem e.g:
     * -----BEGIN RSA PRIVATE KEY-----
     * S3O3BrpoUCwYTF5Vn9EQhkjsu8s...
     * -----END RSA PRIVATE KEY-----
     * Or the name of the file that contains the private key,
     * this file should be located in: $CFG->dataroot . '/objectfs/' e.g:
     * $CFG->dataroot . '/objectfs/' . cloudfront.pem
     *
     * @param string $cloudfrontprivatekey
     * @return bool|resource
     */
    public static function parse_cloudfront_private_key($cloudfrontprivatekey) {
        global $CFG;
        $pemfile = $CFG->dataroot . '/objectfs/' . $cloudfrontprivatekey;
        if (file_exists($pemfile) && is_readable($pemfile)) {
            $cloudfrontprivatekey = 'file://' . $pemfile;
        }
        return openssl_pkey_get_private($cloudfrontprivatekey);
    }

    /**
     * Check if file extension is whitelisted.
     * @param string $filename
     * @return bool
     * @throws \dml_exception
     * @throws \coding_exception
     */
    public static function is_extension_whitelisted($filename) {
        $config = self::get_objectfs_config();
        if (empty($config->signingwhitelist)) {
            return false;
        }
        $classexists = class_exists('\core_form\filetypes_util');
        if (!$classexists) {
            throw new \coding_exception(get_string('backportfiletypesclass', 'tool_objectfs'));
        }
        $util = new \core_form\filetypes_util();
        $whitelist = $util->normalize_file_types($config->signingwhitelist);
        if (empty($whitelist)) {
            return false;
        }
        $extension = strtolower('.' . pathinfo($filename, PATHINFO_EXTENSION));
        return $util->is_whitelisted($extension, $whitelist);
    }

    /**
     * Check if all file extensions are whitelisted.
     *
     * @return bool
     * @throws \dml_exception
     */
    public static function all_extensions_whitelisted() {
        $config = self::get_objectfs_config();
        if (!empty($config->signingwhitelist) && $config->signingwhitelist == '*') {
            return true;
        }
        return false;
    }

    /**
     * Check if '$CFG->alternative_file_system_class' is properly set.
     * @return bool
     */
    public static function check_file_storage_filesystem() {
        $fs = get_file_storage();
        $objectfilesystem = object_file_system::class;
        if ($fs->get_file_system() instanceof $objectfilesystem) {
            return true;
        }
        return false;
    }

    /**
     * Returns the list of installed and available filesystems.
     *
     * @return array
     * @throws \coding_exception
     */
    public static function get_available_fs_list() {
        $result[''] = get_string('pleaseselect', OBJECTFS_PLUGIN_NAME);

        $filesystems['\tool_objectfs\azure_file_system'] = '\tool_objectfs\azure_file_system';
        $filesystems['\tool_objectfs\digitalocean_file_system'] = '\tool_objectfs\digitalocean_file_system';
        $filesystems['\tool_objectfs\s3_file_system'] = '\tool_objectfs\s3_file_system';
        $filesystems['\tool_objectfs\swift_file_system'] = '\tool_objectfs\swift_file_system';

        foreach ($filesystems as $filesystem) {
            $clientclass = self::get_client_classname_from_fs($filesystem);
            $client = new $clientclass(null);

            if ($client && $client->get_availability()) {
                $result[$filesystem] = $filesystem;
            }
        }
        return $result;
    }

    /**
     * Returns client classname for given filesystem.
     *
     * @param string $filesystem File system
     * @return string
     */
    public static function get_client_classname_from_fs($filesystem) {
        $clientclass = str_replace('_file_system', '', $filesystem);
        return str_replace('tool_objectfs\\', 'tool_objectfs\\local\\store\\', $clientclass.'\\client');
    }

    /**
     * Returns given header from headers set.
     *
     * @param array $headers An indexed or associative array with the headers.
     * @param string $search
     *
     * @return string header.
     */
    public static function get_header($headers, $search) {
        foreach ($headers as $key => $value) {
            if (is_int($key)) {
                // Indexed array where element value looks like "Header name: Header value".
                $found = strpos(strtolower($value), strtolower($search).':', 0);
                if ($found !== false) {
                    return substr($value, strlen($search) + 2);
                }
            } else {
                // Associative array where element key is a Header name and element value is Header value.
                if (strtolower($key) === strtolower($search)) {
                    return $value;
                }
            }
        }
        return '';
    }

    /**
     * Returns file name from Content-Disposition header.
     *
     * @param  string $header Content-Disposition header
     * @return string
     */
    public static function get_filename_from_header($header) {
        $filename = '';
        if (!empty($header)) {
            $fparts = explode('; ', $header);
            if (!empty($fparts[1])) {
                // Get the actual filename.
                $filename = str_replace('filename=', '', $fparts[1]);
                // Remove the quotes.
                $filename = str_replace('"', '', $filename);
            }
        }
        return $filename;
    }
}
