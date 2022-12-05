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

namespace tool_objectfs\local\store;

use \tool_objectfs\tests\test_digitalocean_integration_client as digitaloceanclient;
use \tool_objectfs\tests\test_s3_integration_client as s3client;

/**
 * Client tests.
 * @package   tool_objectfs
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class clients_test extends \advanced_testcase {

    /**
     * Data provider for testing s3 client connection.
     *
     * @return \array[][]
     */
    public function s3_client_test_connection_if_not_configured_properly_data_provider() {
        return [
            [[]],
            [['s3_bucket' => '', 's3_region' => 'test', 's3_usesdkcreds' => 0, 's3_key' => 'test', 's3_secret' => 'test']],
            [['s3_bucket' => 'test', 's3_region' => '', 's3_usesdkcreds' => 0, 's3_key' => 'test', 's3_secret' => 'test']],
            [['s3_bucket' => '', 's3_region' => 'test', 's3_usesdkcreds' => 1, 's3_key' => 'test', 's3_secret' => 'test']],
            [['s3_bucket' => 'test', 's3_region' => '', 's3_usesdkcreds' => 1, 's3_key' => 'test', 's3_secret' => 'test']],
            [['s3_bucket' => 'test', 's3_region' => 'test', 's3_usesdkcreds' => 0, 's3_key' => '', 's3_secret' => '']],
            [['s3_bucket' => 'test', 's3_region' => 'test', 's3_usesdkcreds' => 0, 's3_key' => 'test', 's3_secret' => '']],
            [['s3_bucket' => 'test', 's3_region' => 'test', 's3_usesdkcreds' => 0, 's3_key' => '', 's3_secret' => 'test']],
        ];
    }

    /**
     * Test client without configuration.
     *
     * @dataProvider s3_client_test_connection_if_not_configured_properly_data_provider
     * @param array $config Config to test on.
     * @covers \tool_objectfs\local\store\s3\client
     */
    public function test_s3_client_test_connection_if_not_configured_properly(array $config) {
        if (!empty($config)) {
            $otherproperties = ['expirationtime', 'presignedminfilesize', 'enablepresignedurls', 'signingmethod', 'key_prefix'];
            $config = (object)$config;
            foreach ($otherproperties as $name) {
                $config->$name = '';
            }
        }

        $s3client = new s3client($config);
        $testresults = $s3client->test_connection();
        $this->assertFalse($testresults->success);
        $this->assertSame(get_string('settings:notconfigured', 'tool_objectfs'), $testresults->details);
    }

    /**
     * Data provider for testing digitalocean client connection.
     *
     * @return \array[][]
     */
    public function digitalocean_client_test_connection_if_not_configured_properly_data_provider() {
        return [
            [[]],
            [['do_key' => '', 'do_secret' => '', 'do_region' => '']],
            [['do_key' => 'test', 'do_secret' => '', 'do_region' => '']],
            [['do_key' => '', 'do_secret' => 'test', 'do_region' => '']],
            [['do_key' => '', 'do_secret' => '', 'do_region' => 'test']],
            [['do_key' => 'test', 'do_secret' => 'test', 'do_region' => '']],
            [['do_key' => '', 'do_secret' => 'test', 'do_region' => 'test']],
            [['do_key' => 'test', 'do_secret' => '', 'do_region' => 'test']],
        ];
    }

    /**
     * Test client without configuration.
     *
     * @dataProvider digitalocean_client_test_connection_if_not_configured_properly_data_provider
     * @param array $config Config to test on.
     * @covers \tool_objectfs\local\store\digitalocean\client
     */
    public function test_digitalocean_client_test_connection_if_not_configured_properly(array $config) {
        if (!empty($config)) {
            $otherproperties = ['do_space'];
            $config = (object)$config;
            foreach ($otherproperties as $name) {
                $config->$name = '';
            }
        }

        $s3client = new digitaloceanclient($config);
        $testresults = $s3client->test_connection();
        $this->assertFalse($testresults->success);
        $this->assertSame(get_string('settings:notconfigured', 'tool_objectfs'), $testresults->details);
    }

}
