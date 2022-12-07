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

namespace tool_objectfs\privacy;

/**
 * Privacy test for Objectfs.
 *
 * @package    tool_objectfs
 * @category   test
 * @copyright  2020 Mikhail Golenkov <mikhailgolenkov@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \tool_objectfs\privacy\provider
 */
class privacy_test extends \advanced_testcase {

    /**
     * Check the privacy provider implements null_provider.
     */
    public function test_provider_implements_null_provider() {
        // Privacy classes may not exist in older Moodles/Totara.
        if (interface_exists('\core_privacy\local\metadata\null_provider')) {
            $provider = new provider();
            $this->assertInstanceOf('\core_privacy\local\metadata\null_provider', $provider);
        } else {
            $this->markTestSkipped('Interface not found: \core_privacy\local\metadata\null_provider');
        }
    }
}
