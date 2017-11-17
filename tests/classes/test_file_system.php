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
 * object_file_system abstract class.
 *
 * Remote object storage providers extent this class.
 * At minimum you need to impletment get_remote_client.
 *
 * @package   tool_objectfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\tests;

defined('MOODLE_INTERNAL') || die();

use tool_objectfs\object_file_system;
require_once(__DIR__ . '/test_client.php');
require_once(__DIR__ . '/test_s3_integration_client.php');
require_once(__DIR__ . '/test_azure_integration_client.php');

class test_file_system extends object_file_system {

    protected function get_external_client($config) {
        global $CFG;
        if (isset($CFG->phpunit_objectfs_s3_integration_test_credentials)) {
            $credentials = $CFG->phpunit_objectfs_s3_integration_test_credentials;
            $config->s3_key = $credentials['s3_key'];
            $config->s3_secret = $credentials['s3_secret'];
            $config->s3_bucket = $credentials['s3_bucket'];
            $config->s3_region = $credentials['s3_region'];
            set_objectfs_config($config);
            $client = new test_s3_integration_client($config);
        } else if (isset($CFG->phpunit_objectfs_azure_integration_test_credentials)) {
            $credentials = $CFG->phpunit_objectfs_azure_integration_test_credentials;
            $config->azure_accountname = $credentials['azure_accountname'];
            $config->azure_container = $credentials['azure_container'];
            $config->azure_sastoken = $credentials['azure_sastoken'];
            set_objectfs_config($config);
            $client = new test_azure_integration_client($config);
        } else {
            $client = new test_client($config);
        }

        return $client;
    }
}
