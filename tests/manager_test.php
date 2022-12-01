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
 * tool_objectfs manager class tests.
 *
 * @package   tool_objectfs
 * @author    Mikhail Golenkov <mikhailgolenkov@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\tests;

use tool_objectfs\local\manager;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/tool_objectfs_testcase.php');

class manager_test extends tool_objectfs_testcase {

    /**
     * Data provider for test_all_extensions_whitelisted().
     *
     * @return array
     */
    public function test_all_extensions_whitelisted_provider() {
        return [
            [null, false],
            ['', false],
            ['audio', false],
            ['archive,audio', false],
            ['*', true],
        ];
    }

    /**
     * Test all_extensions_whitelisted().
     *
     * @dataProvider test_all_extensions_whitelisted_provider
     *
     * @param  mixed  $signingwhitelist  Config setting
     * @param  bool   $result            Expected result
     * @throws \dml_exception
     */
    public function test_all_extensions_whitelisted($signingwhitelist, $result) {
        if (isset($signingwhitelist)) {
            set_config('signingwhitelist', $signingwhitelist, 'tool_objectfs');
        }
        $this->assertEquals($result, manager::all_extensions_whitelisted());
    }

    /**
     * Data provider for test_is_extension_whitelisted().
     *
     * @return array
     */
    public function is_extension_whitelisted_provider() {
        return [
            [null, 'file.tar', false],
            ['', 'file.tar', false],
            ['*', 'file.tar', true],
            ['audio', 'file.tar', false],
            ['archive', 'file.tar', true],
            ['archive,audio', 'file.tar', true],
            ['audio,image', 'file.tar', false],
        ];
    }

    /**
     * Test is_extension_whitelisted().
     *
     * @dataProvider is_extension_whitelisted_provider
     *
     * @param  mixed   $signingwhitelist  Config setting
     * @param  string  $filename          File name
     * @param  bool    $result            Expected result
     * @throws \dml_exception
     */
    public function test_is_extension_whitelisted($signingwhitelist, $filename, $result) {
        if (isset($signingwhitelist)) {
            set_config('signingwhitelist', $signingwhitelist, 'tool_objectfs');
        }
        $this->assertEquals($result, manager::is_extension_whitelisted($filename));
    }

    /**
     * Data provider for test_get_header().
     *
     * @return array
     */
    public function get_header_provider() {
        return [
            [[], '', ''],
            [[], 'Missing header', ''],
            // Test indexed array.
            [['Content-Type: text'], 'Content-Type', 'text'],
            [['Content-Disposition: inline; filename="file.mp4"'], 'Content-Disposition', 'inline; filename="file.mp4"'],
            [['Content-Ranges: bytes 50823168-69632911/69632912'], 'Content-Ranges', 'bytes 50823168-69632911/69632912'],
            [['Content-Type: text', 'Range: bytes=0-499, -500'], 'Range', 'bytes=0-499, -500'],
            [['Content-Type: text', 'Range: bytes=0-499, -500'], 'range', 'bytes=0-499, -500'],
            // Test associative array.
            [['REQUEST_METHOD' => 'GET'], 'REQUEST_METHOD', 'GET'],
            [['REQUEST_METHOD' => 'GET'], 'request_method', 'GET'],
            [['REQUEST_METHOD' => 'GET', 'HTTP_RANGE' => 'bytes=132579328-132619239'], 'HTTP_RANGE', 'bytes=132579328-132619239'],
        ];
    }

    /**
     * Test get_header() method.
     *
     * @dataProvider get_header_provider
     *
     * @param  array   $headers     Headers
     * @param  string  $search      What we are searching for
     * @param  bool    $expected    Expected result
     */
    public function test_get_header($headers, $search, $expected) {
        $actual = manager::get_header($headers, $search);
        $this->assertEquals($expected, $actual);
    }
}
