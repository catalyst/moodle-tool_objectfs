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

        $config->filesystem = '';
        $config->enablepresignedurls = 0;
        $config->expirationtime = 10 * MINSECS;
        $config->presignedminfilesize = 0;

        // S3 file system.
        $config->s3_key = '';
        $config->s3_secret = '';
        $config->s3_bucket = '';
        $config->s3_region = 'us-east-1';

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
        $fsclass = $config->filesystem;
        $client = str_replace('_file_system', '', $fsclass);
        $client = str_replace('tool_objectfs\\', 'tool_objectfs\\local\\store\\', $client.'\\client');

        if (class_exists($client)) {
            return new $client($config);
        }

        return false;
    }

    /**
     * @return mixed
     */
    public static function get_fs_list() {
        $found[''] = 'Please, select';
        $found['\tool_objectfs\azure_file_system'] = '\tool_objectfs\azure_file_system';
        $found['\tool_objectfs\digitalocean_file_system'] = '\tool_objectfs\digitalocean_file_system';
        $found['\tool_objectfs\s3_file_system'] = '\tool_objectfs\s3_file_system';
        $found['\tool_objectfs\swift_file_system'] = '\tool_objectfs\swift_file_system';
        return $found;
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
     * @param string $cloudfrontprivatekey
     * @return bool|resource
     */
    static public function parse_cloudfront_private_key($cloudfrontprivatekey) {
        if (file_exists($cloudfrontprivatekey) && is_readable($cloudfrontprivatekey)) {
            $cloudfrontprivatekey = file_get_contents($cloudfrontprivatekey);
        }
        return openssl_pkey_get_private($cloudfrontprivatekey);
    }

    /**
     * Breaks apart filetype groups into an array of extensions, so they can
     * be granularly filtered.
     *
     * @param array $types array of extensions or types to break apart.
     * @return array array of all extensions in all input groups.
     */
    public static function file_split_types_to_exts($types) {
        $util = new \core_form\filetypes_util();
        $mimetypes = get_mimetypes_array();
        $return = [];
        foreach ($types as $type) {
            // Filter for where group matches type, only keep extension.
            $extensions = array_keys(array_filter($mimetypes, function($element) use ($type) {
                if (isset($element['groups'])) {
                    return in_array($type, $element['groups']);
                }
                return false;
            }));
            $tonormalise = !empty($extensions) ? $extensions : $type;
            $normalised = $util->normalize_file_types($tonormalise);
            // Merge into return array.
            $return += $normalised;
        }
        return $return;
    }
}
