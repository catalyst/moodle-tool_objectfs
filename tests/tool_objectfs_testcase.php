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
 * tool_objectfs test base abstract class.
 * @package   local_catdeleter
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once( __DIR__ . '/../lib.php');
require_once( __DIR__ . '/mock/mock_client.php');
require_once( __DIR__ . '/mock/sss_integration_test_client.php');

abstract class tool_objectfs_testcase extends advanced_testcase {

    protected function save_file_to_local_storage_from_string($filesize = 10, $filename = 'test.txt', $filecontent = 'test') {
        global $DB;
        $fs = get_file_storage();

        $syscontext = context_system::instance();
        $component = 'core';
        $filearea  = 'unittest';
        $itemid    = 0;
        $filepath  = '/';
        $sourcefield = 'Copyright stuff';

        $filerecord = array(
            'contextid' => $syscontext->id,
            'component' => $component,
            'filearea'  => $filearea,
            'itemid'    => $itemid,
            'filepath'  => $filepath,
            'filename'  => $filename,
            'source'    => $sourcefield,
        );
        $file = $fs->create_file_from_string($filerecord, $filecontent);

        // Above method does not set a file size, we do this so we do and have control over it.
        $DB->set_field('files', 'filesize', $filesize, array('contenthash' => $file->get_contenthash()));

        return $file;
    }

    /**
     * If integration_test_config.php is set, we use the integration test client.
     * Else we use the mock client.
     *
     * @return object sss test client.
     */
    protected function get_test_client() {
        if (file_exists(__DIR__ . '/mock/integration_test_config.php')) {
            $integrationconfig = include('mock/integration_test_config.php');
            $client = new sss_integration_test_client($integrationconfig);
        } else {
            $client = new mock_client();
        }
        return $client;
    }

    protected function move_file_to_sss($file, $location = OBJECT_LOCATION_REMOTE) {
        $contenthash = $file->get_contenthash();
        $localpath = $this->get_local_fullpath_from_hash($contenthash);
        $md5 = md5_file($localpath);
        $ssspath = $this->client->get_sss_fullpath_from_hash($contenthash);
        copy($localpath, $ssspath);
        if ($location == OBJECT_LOCATION_REMOTE) {
            unlink($localpath);
        }
        log_file_location($contenthash, $location, $md5);
    }

    protected function save_file_to_local_storage_from_pathname($pathname) {
        global $DB;
        $fs = get_file_storage();

        $syscontext = context_system::instance();
        $component = 'core';
        $filearea  = 'unittest';
        $itemid    = 0;
        $filepath  = '/';
        $sourcefield = 'Copyright stuff';

        $filerecord = array(
            'contextid' => $syscontext->id,
            'component' => $component,
            'filearea'  => $filearea,
            'itemid'    => $itemid,
            'filepath'  => $filepath,
            'filename'  => $pathname,
            'source'    => $sourcefield,
        );
        $file = $fs->create_file_from_pathname($filerecord, $pathname);
        return $file;
    }

    protected function generate_config($sizethreshold = 0, $minimumage = -10, $maxtaskruntime = 60, $deletelocal = 1, $consistencydelay = 0) {
        $config = new stdClass();
        $config->enabled = 1;
        $config->key = 123;
        $config->secret = 123;
        $config->bucket = 'test-bucket';
        $config->region = 'ap-southeast-2';
        $config->sizethreshold = $sizethreshold * 1024; // Convert from kb.
        $config->minimumage = $minimumage;
        $config->consistencydelay = $consistencydelay;
        $config->logging = 1;
        $config->maxtaskruntime = $maxtaskruntime;
        $config->deletelocal = $deletelocal;
        $config->prefersss = 0;
        save_sss_config_data($config);
        return $config;
    }

    /**
     * Returns local fullpath. We redifine this function here so
     * that our file moving functions can exist outside of the fsapi.
     * Which means filesystem_handler_class does not need to be set for them
     * to function.
     *
     * @param  string $contenthash contenthash
     * @return string fullpath to local object.
     */
    protected function get_local_fullpath_from_hash($contenthash) {
        global $CFG;
        if (isset($CFG->filedir)) {
            $filedir = $CFG->filedir;
        } else {
            $filedir = $CFG->dataroot.'/filedir';
        }
        $l1 = $contenthash[0] . $contenthash[1];
        $l2 = $contenthash[2] . $contenthash[3];
        return "$filedir/$l1/$l2/$contenthash";
    }
}
