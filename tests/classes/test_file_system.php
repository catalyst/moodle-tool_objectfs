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

use tool_objectfs\config\singleton as cfg;
use tool_objectfs\local\store\object_file_system;

require_once(__DIR__ . '/test_client.php');
require_once(__DIR__ . '/test_config.php');
require_once(__DIR__ . '/test_s3_integration_client.php');
require_once(__DIR__ . '/test_azure_integration_client.php');

class test_file_system extends object_file_system {

    static private $uploadsize;

    protected function initialise_external_client(cfg $config) {
        global $CFG;
        if (isset($CFG->phpunit_objectfs_s3_integration_test_credentials)) {
            $credentials = $CFG->phpunit_objectfs_s3_integration_test_credentials;
            $cfg['s3_key'] = $credentials['s3_key'];
            $cfg['s3_secret'] = $credentials['s3_secret'];
            $cfg['s3_bucket'] = $credentials['s3_bucket'];
            $cfg['s3_region'] = $credentials['s3_region'];
            cfg::set_config($cfg);
            $client = new test_s3_integration_client(cfg::instance());
        } else if (isset($CFG->phpunit_objectfs_azure_integration_test_credentials)) {
            $credentials = $CFG->phpunit_objectfs_azure_integration_test_credentials;
            $cfg['azure_accountname'] = $credentials['azure_accountname'];
            $cfg['azure_container'] = $credentials['azure_container'];
            $cfg['azure_sastoken'] = $credentials['azure_sastoken'];
            cfg::set_config($cfg);
            $client = new test_azure_integration_client($config);
        } else if (isset($CFG->phpunit_objectfs_swift_integration_test_credentials)) {
            $credentials = $CFG->phpunit_objectfs_swift_integration_test_credentials;
            $cfg['openstack_authurl'] = $credentials['openstack_authurl'];
            $cfg['openstack_region'] = $credentials['openstack_region'];
            $cfg['openstack_container'] = $credentials['openstack_container'];
            $cfg['openstack_username'] = $credentials['openstack_username'];
            $cfg['openstack_password'] = $credentials['openstack_password'];
            $cfg['openstack_tenantname'] = $credentials['openstack_tenantname'];
            $cfg['openstack_projectid'] = $credentials['openstack_projectid'];
            cfg::set_config($cfg);
            $client = new test_swift_integration_client($config);
        } else {
            $client = new test_client($config);
        }
        self::$uploadsize = $client->get_maximum_upload_size();
        return $client;
    }

    /**
     * @return float|int
     */
    static public function get_maximum_upload_size() {
        return self::$uploadsize;
    }
}
