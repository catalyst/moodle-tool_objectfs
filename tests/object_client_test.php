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

namespace tool_objectfs\tests;

defined('MOODLE_INTERNAL') || die();

use advanced_testcase;
use tool_objectfs\local\manager;

require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/classes/test_client.php');

class object_client_testcase extends advanced_testcase {

    protected function setUp() {
        $this->resetAfterTest(true);
    }

    public function test_notification() {
        global $CFG, $SESSION;
        $config = manager::get_objectfs_config();
        $client = new test_client($config);
        $client->notification('Success');
        if ($CFG->branch > 30) {
            self::assertObjectHasAttribute('notifications', $SESSION);
            self::assertCount(1, $SESSION->notifications);
        }
    }
}
