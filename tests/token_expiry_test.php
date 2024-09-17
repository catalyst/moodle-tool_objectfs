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

namespace tool_objectfs;

use core\check\result;
use tool_objectfs\check\token_expiry;
use tool_objectfs\local\manager;
use tool_objectfs\tests\testcase;

/**
 * Token expiry check test.
 *
 * @covers \tool_objectfs\check\token_expiry
 * @package   tool_objectfs
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class token_expiry_test extends testcase {
    /**
     * Provides to test_get_result
     * @return array
     */
    public static function get_result_provider(): array {
        return [
            'ok' => [
                'expiry' => time() + 10 * DAYSECS,
                'warnperiod' => 5 * DAYSECS,
                'expectedresult' => result::OK,
            ],
            'warning' => [
                'expiry' => time() + DAYSECS,
                'warnperiod' => 5 * DAYSECS,
                'expectedresult' => result::WARNING,
            ],
            'expired' => [
                'expiry' => time() - DAYSECS,
                'warnperiod' => 5 * DAYSECS,
                'expectedresult' => result::CRITICAL,
            ],
        ];
    }

    /**
     * Tests getting check result
     * @param int $expirytime time to use as the tokens expiry
     * @param int $warnperiod period to set for warning about token expiry
     * @param string $expectedresult one of the result:: constants that is expected to be returned.
     * @dataProvider get_result_provider
     */
    public function test_get_result(int $expirytime, int $warnperiod, string $expectedresult) {
        global $CFG;
        $config = manager::get_objectfs_config();
        $config->filesystem = '\\tool_objectfs\\tests\\test_file_system';
        manager::set_objectfs_config($config);

        $CFG->objectfs_phpunit_token_expiry_time = $expirytime;
        set_config('tokenexpirywarnperiod', $warnperiod, 'tool_objectfs');

        $check = new token_expiry();
        $result = $check->get_result();
        $this->assertEquals($expectedresult, $result->get_status());
    }
}
