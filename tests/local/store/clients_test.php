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

use tool_objectfs\tests\test_digitalocean_integration_client as digitaloceanclient;
use tool_objectfs\tests\test_s3_integration_client as s3client;
use tool_objectfs\tests\test_azure_integration_client as azureclient;

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
    public static function s3_client_test_connection_if_not_configured_properly_data_provider(): array {
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
    public static function digitalocean_client_test_connection_if_not_configured_properly_data_provider(): array {
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

    /**
     * Provides values to azure_client_get_token_expiry test
     * @return array
     */
    public static function azure_client_get_token_expiry_provider(): array {
        return [
            'good token' => [
                'sastoken' => 'sp=racwl&st=2024-09-13T01:11:06Z&se=2024-12-30T09:11:06Z&spr=https&sv=2022-11-02&sr=c&sig=abcd',
                'expectedtime' => 1735549866,
            ],
            'malformed se' => [
                'sastoken' => 'sp=racwl&st=2024-09-13T01:11:06Z&se=-12-30T09:11:06Z&spr=https&sv=2022-11-02&sr=c&sig=abcd',
                'expectedtime' => 0,
            ],
            'missing se' => [
                'sastoken' => 'sp=racwl&st=2024-09-13T01:11:06Z&spr=https&sv=2022-11-02&sr=c&sig=abcd',
                'expectedtime' => 0,
            ],
            'no token' => [
                'sastoken' => '',
                'expectedtime' => -1,
            ],
        ];
    }

    /**
     * Tests azure client correctly extracts token expiry time
     * @param string $sastoken
     * @param int $expectedtime
     * @dataProvider azure_client_get_token_expiry_provider
     */
    public function test_azure_client_get_token_expiry(string $sastoken, int $expectedtime) {
        $config = (object) [
            'azure_container' => 'test',
            'azure_accountname' => 'test',
            'azure_sastoken' => $sastoken,
        ];
        $azureclient = new azureclient($config);
        $this->assertEquals($expectedtime, $azureclient->get_token_expiry_time());
    }
}
