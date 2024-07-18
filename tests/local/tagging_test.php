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

namespace tool_objectfs\local;

use coding_exception;
use Throwable;
use tool_objectfs\local\manager;
use tool_objectfs\local\tag\tag_manager;
use tool_objectfs\local\tag\tag_source;
use tool_objectfs\tests\testcase;

/**
 * Tests tagging
 *
 * @package   tool_objectfs
 * @author    Matthew Hilton <matthewhilton@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tagging_test extends testcase {
    /**
     * Tests get_defined_tag_sources
     * @covers \tool_objectfs\local\tag_manager::get_defined_tag_sources
     */
    public function test_get_defined_tag_sources() {
        $sources = tag_manager::get_defined_tag_sources();
        $this->assertIsArray($sources);

        // Both AWS and Azure limit 10 tags per object, so ensure never more than 10 sources defined.
        $this->assertLessThanOrEqual(10, count($sources));
    }

    /**
     * Provides values to various tag source tests
     * @return array
     */
    public static function tag_source_provider(): array {
        $sources = tag_manager::get_defined_tag_sources();
        $tests = [];

        foreach ($sources as $source) {
            $tests[$source->get_identifier()] = [
                'source' => $source,
            ];
        }

        return $tests;
    }

    /**
     * Tests the source identifier
     * @param tag_source $source
     * @dataProvider tag_source_provider
     * @covers \tool_objectfs\local\tag_source::get_identifier
     */
    public function test_tag_sources_identifier(tag_source $source) {
        $count = strlen($source->get_identifier());

        // Ensure < 32 chars, the max length as defined in our docs.
        $this->assertLessThan(32, $count);
        $this->assertGreaterThan(0, $count);
    }

    /**
     * Tests the source value
     * @param tag_source $source
     * @dataProvider tag_source_provider
     * @covers \tool_objectfs\local\tag_source::get_value_for_contenthash
     */
    public function test_tag_sources_value(tag_source $source) {
        $file = $this->create_duplicated_object('tag source value test ' . $source->get_identifier());
        $value = $source->get_value_for_contenthash($file->contenthash);

        // Null value - allowed, but means we cannot test.
        if (is_null($value)) {
            return;
        }

        $count = strlen($value);

        // Ensure < 128 chars, the max length as defined in our docs.
        $this->assertLessThan(128, $count);
        $this->assertGreaterThan(0, $count);
    }

    /**
     * Provides values to test_is_tagging_enabled_and_supported
     * @return array
     */
    public static function is_tagging_enabled_and_supported_provider(): array {
        return [
            'neither config nor fs supports' => [
                'enabledinconfig' => false,
                'supportedbyfs' => false,
                'expected' => false,
            ],
            'enabled in config but fs does not support' => [
                'enabledinconfig' => true,
                'supportedbyfs' => false,
                'expected' => false,
            ],
            'enabled in config and fs does  support' => [
                'enabledinconfig' => true,
                'supportedbyfs' => true,
                'expected' => true,
            ],
        ];
    }

    /**
     * Tests is_tagging_enabled_and_supported
     * @param bool $enabledinconfig if tagging feature is turned on
     * @param bool $supportedbyfs if the filesystem supports tagging
     * @param bool $expected expected return result
     * @dataProvider is_tagging_enabled_and_supported_provider
     * @covers \tool_objectfs\local\tag_manager::is_tagging_enabled_and_supported
     */
    public function test_is_tagging_enabled_and_supported(bool $enabledinconfig, bool $supportedbyfs, bool $expected) {
        global $CFG;
        // Set config.
        set_config('taggingenabled', $enabledinconfig, 'tool_objectfs');

        // Set supported by fs.
        $config = manager::get_objectfs_config();
        $config->taggingenabled = $enabledinconfig;
        $config->enabletasks = true;
        $config->filesystem = '\\tool_objectfs\\tests\\test_file_system';
        manager::set_objectfs_config($config);
        $CFG->phpunit_objectfs_supports_object_tagging = $supportedbyfs;

        $this->assertEquals($expected, tag_manager::is_tagging_enabled_and_supported());
    }

    /**
     * Tests gather_object_tags_for_upload
     * @covers \tool_objectfs\local\tag_manager::gather_object_tags_for_upload
     */
    public function test_gather_object_tags_for_upload() {
        $object = $this->create_duplicated_object('gather tags for upload test');
        $tags = tag_manager::gather_object_tags_for_upload($object->contenthash);

        $this->assertArrayHasKey('mimetype', $tags);
        $this->assertArrayHasKey('environment', $tags);
        $this->assertEquals('text', $tags['mimetype']);
        $this->assertEquals('test', $tags['environment']);
    }

    /**
     * Tests store_tags_locally
     * @covers \tool_objectfs\local\tag_manager::store_tags_locally
     */
    public function test_store_tags_locally() {
        global $DB;

        $tags = [
            'test1' => 'abc',
            'test2' => 'xyz',
        ];
        $hash = 'thisisatest';

        // Ensure no tags for hash intially.
        $this->assertEmpty($DB->get_records('tool_objectfs_object_tags', ['contenthash' => $hash]));

        // Store.
        tag_manager::store_tags_locally($hash, $tags);

        // Confirm they are stored.
        $queriedtags = $DB->get_records('tool_objectfs_object_tags', ['contenthash' => $hash]);
        $this->assertCount(2, $queriedtags);
        $tagtimebefore = current($queriedtags)->timemodified;

        // Re-store, confirm times changed.
        $this->waitForSecond();
        tag_manager::store_tags_locally($hash, $tags);
        $queriedtags = $DB->get_records('tool_objectfs_object_tags', ['contenthash' => $hash]);
        $tagtimeafter = current($queriedtags)->timemodified;

        $this->assertNotSame($tagtimebefore, $tagtimeafter);
    }

    /**
     * Provides values to test_get_objects_needing_sync
     * @return array
     */
    public static function get_objects_needing_sync_provider(): array {
        return [
            'duplicated, needs sync' => [
                'location' => OBJECT_LOCATION_DUPLICATED,
                'status' => tag_manager::SYNC_STATUS_NEEDS_SYNC,
                'expectedneedssync' => true,
            ],
            'remote, needs sync' => [
                'location' => OBJECT_LOCATION_EXTERNAL,
                'status' => tag_manager::SYNC_STATUS_NEEDS_SYNC,
                'expectedneedssync' => true,
            ],
            'local, needs sync' => [
                'location' => OBJECT_LOCATION_LOCAL,
                'status' => tag_manager::SYNC_STATUS_NEEDS_SYNC,
                'expectedneedssync' => false,
            ],
            'duplicated, does not need sync' => [
                'location' => OBJECT_LOCATION_DUPLICATED,
                'status' => tag_manager::SYNC_STATUS_SYNC_NOT_REQUIRED,
                'expectedneedssync' => false,
            ],
            'local, does not need sync' => [
                'location' => OBJECT_LOCATION_LOCAL,
                'status' => tag_manager::SYNC_STATUS_SYNC_NOT_REQUIRED,
                'expectedneedssync' => false,
            ],
            'duplicated, sync error' => [
                'location' => OBJECT_LOCATION_DUPLICATED,
                'status' => tag_manager::SYNC_STATUS_ERROR,
                'expectedneedssync' => false,
            ],
            'local, sync error' => [
                'location' => OBJECT_LOCATION_LOCAL,
                'status' => tag_manager::SYNC_STATUS_ERROR,
                'expectedneedssync' => false,
            ],
        ];
    }

    /**
     * Tests get_objects_needing_sync
     * @param int $location object location
     * @param int $syncstatus sync status to set on object record
     * @param bool $expectedneedssync if the object should be included in the return of the function
     * @dataProvider get_objects_needing_sync_provider
     * @covers \tool_objectfs\local\tag_manager::get_objects_needing_sync
     */
    public function test_get_objects_needing_sync(int $location, int $syncstatus, bool $expectedneedssync) {
        global $DB;

        // Create the test object at the required location.
        switch ($location) {
            case OBJECT_LOCATION_DUPLICATED:
                $object = $this->create_duplicated_object('tagging test object duplicated');
                break;
            case OBJECT_LOCATION_LOCAL:
                $object = $this->create_local_object('tagging test object local');
                break;
            case OBJECT_LOCATION_EXTERNAL:
                $object = $this->create_remote_object('tagging test object remote');
                break;
            default:
                throw new coding_exception("Object location not handled in test");
        }

        // Set the sync status.
        $DB->set_field('tool_objectfs_objects', 'tagsyncstatus', $syncstatus, ['id' => $object->id]);

        // Check if it is included in the list.
        $needssync = tag_manager::get_objects_needing_sync(1);

        if ($expectedneedssync) {
            $this->assertContains($object->contenthash, $needssync);
        } else {
            $this->assertNotContains($object->contenthash, $needssync);
        }
    }

    /**
     * Tests the limit input to get_objects_needing_sync
     * @covers \tool_objectfs\local\tag_manager::get_objects_needing_sync
     */
    public function test_get_objects_needing_sync_limit() {
        global $DB;

        // Create two duplicated objects needing sync.
        $object = $this->create_duplicated_object('sync limit test duplicated');
        $DB->set_field('tool_objectfs_objects', 'tagsyncstatus', tag_manager::SYNC_STATUS_NEEDS_SYNC, ['id' => $object->id]);
        $object = $this->create_remote_object('sync limit test remote');
        $DB->set_field('tool_objectfs_objects', 'tagsyncstatus', tag_manager::SYNC_STATUS_NEEDS_SYNC, ['id' => $object->id]);

        // Ensure a limit of 2 returns 2, and limit of 1 returns 1.
        $this->assertCount(2, tag_manager::get_objects_needing_sync(2));
        $this->assertCount(1, tag_manager::get_objects_needing_sync(1));
    }

    /**
     * Test get_tag_summary_html
     * @covers \tool_objectfs\local\tag_manager::get_tag_summary_html
     */
    public function test_get_tag_summary_html() {
        // Quick test just to ensure it generates and nothing explodes.
        $html = tag_manager::get_tag_summary_html();
        $this->assertIsString($html);
    }

    /**
     * Tests when fails to sync object tags, that the sync status is updated to SYNC_STATUS_ERROR.
     */
    public function test_object_tag_sync_error() {
        global $CFG, $DB;

        // Setup FS for tagging.
        $config = manager::get_objectfs_config();
        $config->taggingenabled = true;
        $config->enabletasks = true;
        $config->filesystem = '\\tool_objectfs\\tests\\test_file_system';
        manager::set_objectfs_config($config);
        $CFG->phpunit_objectfs_supports_object_tagging = true;
        $this->assertTrue(tag_manager::is_tagging_enabled_and_supported());

        // Create a good duplicated object.
        $object = $this->create_duplicated_object('sync limit test duplicated');
        $status = $DB->get_field('tool_objectfs_objects', 'tagsyncstatus', ['id' => $object->id]);
        $this->assertEquals(tag_manager::SYNC_STATUS_SYNC_NOT_REQUIRED, $status);

        // Now try push tags, but trigger a simulated tag set error.
        $CFG->phpunit_objectfs_simulate_tag_set_error = true;
        $didthrow = false;
        try {
            $this->filesystem->push_object_tags($object->contenthash);
        } catch (Throwable $e) {
            $didthrow = true;
        }
        $this->assertTrue($didthrow);

        // Ensure tag sync status set to error.
        $status = $DB->get_field('tool_objectfs_objects', 'tagsyncstatus', ['id' => $object->id]);
        $this->assertEquals(tag_manager::SYNC_STATUS_ERROR, $status);
    }
}
