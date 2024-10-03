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

namespace tool_objectfs\check;

use core\check\result;
use tool_objectfs\check\tagging_sync_status;
use tool_objectfs\local\tag\tag_manager;
use tool_objectfs\tests\tool_objectfs_testcase;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../tool_objectfs_testcase.php');

/**
 * Tagging sync status check tests
 *
 * @package   tool_objectfs
 * @author    Matthew Hilton <matthewhilton@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \tool_objectfs\check\tagging_sync_status
 */
class tagging_sync_status_test extends tool_objectfs_testcase {
    /**
     * Tests scenario that returns N/A
     */
    public function test_get_result_na() {
        // Not enabled by default, should return N/A.
        $check = new tagging_sync_status();
        $this->assertEquals(result::NA, $check->get_result()->get_status());
    }

    /**
     * Test scenario that returns OK
     */
    public function test_get_result_ok() {
        $this->enable_filesystem_and_set_tagging(true);
        $object = $this->create_remote_object();
        tag_manager::mark_object_tag_sync_status($object->contenthash, tag_manager::SYNC_STATUS_COMPLETE);

        // All objects OK, should return ok.
        $check = new tagging_sync_status();
        $this->assertEquals(result::OK, $check->get_result()->get_status());
    }

    /**
     * Tests scenario that returns WARNING
     */
    public function test_get_result_warning() {
        $this->enable_filesystem_and_set_tagging(true);
        $object = $this->create_remote_object();
        tag_manager::mark_object_tag_sync_status($object->contenthash, tag_manager::SYNC_STATUS_ERROR);

        // An object has error, should return warning.
        $check = new tagging_sync_status();
        $this->assertEquals(result::WARNING, $check->get_result()->get_status());
    }
}
