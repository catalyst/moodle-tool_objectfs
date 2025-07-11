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
use tool_objectfs\tests\testcase;

/**
 * Tagging sync status check tests
 *
 * @package   tool_objectfs
 * @author    Matthew Hilton <matthewhilton@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \tool_objectfs\check\tagging_sync_status
 */
final class tagging_sync_status_test extends testcase {
    /**
     * Tests scenario that returns N/A
     */
    public function test_get_result_na(): void {
        // Not enabled by default, should return N/A.
        $check = new tagging_sync_status();
        $this->assertEquals(result::NA, $check->get_result()->get_status());
    }

    /**
     * Test scenario that returns OK
     */
    public function test_get_result_ok(): void {
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
    public function test_get_result_warning(): void {
        $this->enable_filesystem_and_set_tagging(true);
        $object = $this->create_remote_object();
        tag_manager::mark_object_tag_sync_status($object->contenthash, tag_manager::SYNC_STATUS_ERROR);

        // An object has error, should return warning.
        $check = new tagging_sync_status();
        $this->assertEquals(result::WARNING, $check->get_result()->get_status());
    }

    /**
     * Test caching
     */
    public function test_tag_sync_status_summary_caching() {
        global $DB;
        $this->enable_filesystem_and_set_tagging(true);

        $cache = \cache::make('tool_objectfs', 'tagsummary');
        $cache->delete('tag_sync_status_summary');

        $this->assertEquals($cache->get('tag_sync_status_summary'), false);

        // Test: First call should hit DB, second should use cache.
        $before = $DB->perf_get_queries();
        $result1 = tag_manager::get_tag_sync_status_summary();
        $after1 = $DB->perf_get_queries();

        $this->assertNotEquals($cache->get('tag_sync_status_summary'), false);

        $result2 = tag_manager::get_tag_sync_status_summary();
        $after2 = $DB->perf_get_queries();

        $this->assertGreaterThan($before, $after1, 'First call should hit DB');
        $this->assertEquals($after1, $after2, 'Second call should use cache');
        $this->assertEquals($result1, $result2, 'Cached and DB results should match');
    }
}
