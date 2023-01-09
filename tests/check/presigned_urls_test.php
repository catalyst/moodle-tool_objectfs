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
use tool_objectfs\s3_file_system;

/**
 * Test the presigned_url performance check.
 *
 * @package    tool_objectfs
 * @author     Andrew Madden <andrewmadden@catalyst-au.net>
 * @copyright  2023 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class presigned_urls_test extends \advanced_testcase {

    /**
     * This method runs before every test.
     */
    public function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Test that action link is set up.
     */
    public function test_get_action_link() {
        $check = new presigned_urls();
        $link = $check->get_action_link();
        $this->assertInstanceOf(\action_link::class, $link);
    }

    /**
     * Test fixture files are set up correctly.
     */
    public function test_load_files() {
        $fsstub = $this->createStub(s3_file_system::class);
        $fsstub->method('is_file_readable_externally_by_hash')->willReturn(true);
        $files = presigned_urls::load_files($fsstub);
        $this->assertCount(10, $files);
        // Test array contains files.
        $file = array_shift($files);
        $this->assertInstanceOf(\stored_file::class, $file);
    }

    /**
     * Test warning returned if no filesystem is set up.
     */
    public function test_get_result_with_no_filesystem_setup() {
        // Do not set a file system.
        set_config('filesystem', '', 'tool_objectfs');
        $check = new presigned_urls();
        $result = $check->get_result();
        $this->assertInstanceOf(result::class, $result);
        $this->assertEquals(result::INFO, $result->get_status());
        $this->assertEquals(get_string('check:presigned_urls:infofilesystem', 'tool_objectfs'), $result->get_summary());
    }

    /**
     * Test warning returned if filesystem does not support presigned urls.
     */
    public function test_get_result_with_unsupported_filesystem() {
        // Set file system that does not support presigned urls.
        set_config('filesystem', '\tool_objectfs\azure_file_system', 'tool_objectfs');
        $check = new presigned_urls();
        $result = $check->get_result();
        $this->assertInstanceOf(result::class, $result);
        $this->assertEquals(result::INFO, $result->get_status());
        $this->assertEquals(get_string('check:presigned_urls:infofilesystempresigned', 'tool_objectfs'), $result->get_summary());
    }
}
