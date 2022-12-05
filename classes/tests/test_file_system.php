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

use tool_objectfs\local\manager;
use tool_objectfs\local\store\object_file_system;

class test_file_system extends object_file_system {

    private $maxupload;

    protected function initialise_external_client($config) {
        global $CFG;
        if (isset($CFG->phpunit_objectfs_s3_integration_test_credentials)) {
            $credentials = $CFG->phpunit_objectfs_s3_integration_test_credentials;
            $config->s3_key = $credentials['s3_key'];
            $config->s3_secret = $credentials['s3_secret'];
            $config->s3_bucket = $credentials['s3_bucket'];
            $config->s3_region = $credentials['s3_region'];
            manager::set_objectfs_config($config);
            $client = new test_s3_integration_client($config);
        } else if (isset($CFG->phpunit_objectfs_azure_integration_test_credentials)) {
            $credentials = $CFG->phpunit_objectfs_azure_integration_test_credentials;
            $config->azure_accountname = $credentials['azure_accountname'];
            $config->azure_container = $credentials['azure_container'];
            $config->azure_sastoken = $credentials['azure_sastoken'];
            manager::set_objectfs_config($config);
            $client = new test_azure_integration_client($config);
        } else if (isset($CFG->phpunit_objectfs_swift_integration_test_credentials)) {
            $credentials = $CFG->phpunit_objectfs_swift_integration_test_credentials;
            $config->openstack_authurl = $credentials['openstack_authurl'];
            $config->openstack_region = $credentials['openstack_region'];
            $config->openstack_container = $credentials['openstack_container'];
            $config->openstack_username = $credentials['openstack_username'];
            $config->openstack_password = $credentials['openstack_password'];
            $config->openstack_tenantname = $credentials['openstack_tenantname'];
            $config->openstack_projectid = $credentials['openstack_projectid'];
            manager::set_objectfs_config($config);
            $client = new test_swift_integration_client($config);
        } else {
            $client = new test_client($config);
        }
        $this->maxupload = $client->get_maximum_upload_size();
        return $client;
    }

    /**
     * @return float|int
     */
    public function get_maximum_upload_size() {
        return $this->maxupload;
    }
}
