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

class manager_testcase extends tool_objectfs_testcase {

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
}
