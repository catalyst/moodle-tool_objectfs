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
use moodle_exception;
use Throwable;
use tool_objectfs\local\manager;
use tool_objectfs\local\tag\environment_source;
use tool_objectfs\local\tag\tag_manager;
use tool_objectfs\local\tag\tag_source;
use tool_objectfs\tests\tool_objectfs_testcase;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/tool_objectfs_testcase.php');

/**
 * Tests tagging
 *
 * @package   tool_objectfs
 * @author    Matthew Hilton <matthewhilton@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tagging_test extends tool_objectfs_testcase {
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
            'enabled in config and fs does support' => [
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

        $this->assertArrayHasKey('environment', $tags);
        $this->assertEquals('test', $tags['environment']);
        $this->assertArrayHasKey('location', $tags);
        $this->assertEquals('active', $tags['location']);
    }

    /**
     * Tests gather_object_tags_for_upload when orphaned
     * @covers \tool_objectfs\local\tag_manager::gather_object_tags_for_upload
     */
    public function test_gather_object_tags_for_upload_orphaned() {
        global $DB;
        $object = $this->create_duplicated_object('gather tags for upload test');

        // Change the object record to be orphaned.
        $DB->update_record('tool_objectfs_objects', ['id' => $object->id, 'location' => OBJECT_LOCATION_ORPHANED]);

        $tags = tag_manager::gather_object_tags_for_upload($object->contenthash);

        $this->assertArrayHasKey('environment', $tags);
        $this->assertEquals('test', $tags['environment']);
        $this->assertArrayHasKey('location', $tags);
        $this->assertEquals('orphan', $tags['location']);
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
        $object = $this->create_remote_object();

        // Ensure no tags for hash intially.
        $this->assertEmpty($DB->get_records('tool_objectfs_object_tags', ['objectid' => $object->id]));

        // Store.
        tag_manager::store_tags_locally($object->contenthash, $tags);

        // Confirm they are stored.
        $queriedtags = $DB->get_records('tool_objectfs_object_tags', ['objectid' => $object->id]);
        $this->assertCount(2, $queriedtags);
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
                'status' => tag_manager::SYNC_STATUS_COMPLETE,
                'expectedneedssync' => false,
            ],
            'local, does not need sync' => [
                'location' => OBJECT_LOCATION_LOCAL,
                'status' => tag_manager::SYNC_STATUS_COMPLETE,
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
     * Test get_tag_source_summary_html
     * @covers \tool_objectfs\local\tag_manager::get_tag_source_summary_html
     */
    public function test_get_tag_source_summary_html() {
        // Quick test just to ensure it generates and nothing explodes.
        $html = tag_manager::get_tag_source_summary_html();
        $this->assertIsString($html);
    }

    /**
     * Tests when fails to sync object tags, that the sync status is updated to SYNC_STATUS_ERROR.
     * @covers \tool_objectfs\local\tag_manager
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
        $this->assertEquals(tag_manager::SYNC_STATUS_COMPLETE, $status);

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

    /**
     * Tests tag_manger::get_tag_sync_status_summary
     * @covers \tool_objectfs\local\tag_manager::get_tag_sync_status_summary
     */
    public function test_get_tag_sync_status_summary() {
        // Ensure clean slate before test starts.
        global $DB;
        $DB->delete_records('tool_objectfs_objects');

        // Create an object with each status.
        $object1 = $this->create_local_object('test1');
        $object2 = $this->create_local_object('test2');
        $object3 = $this->create_local_object('test3');

        // Delete the unit test object that is automatically created, it has a filesize of zero.
        $DB->delete_records('tool_objectfs_objects', ['filesize' => 0]);

        tag_manager::mark_object_tag_sync_status($object1->contenthash, tag_manager::SYNC_STATUS_COMPLETE);
        tag_manager::mark_object_tag_sync_status($object2->contenthash, tag_manager::SYNC_STATUS_ERROR);
        tag_manager::mark_object_tag_sync_status($object3->contenthash, tag_manager::SYNC_STATUS_NEEDS_SYNC);

        // Ensure correctly counted.
        $statuses = tag_manager::get_tag_sync_status_summary();
        $this->assertEquals(1, $statuses[tag_manager::SYNC_STATUS_COMPLETE]->statuscount);
        $this->assertEquals(1, $statuses[tag_manager::SYNC_STATUS_ERROR]->statuscount);
        $this->assertEquals(1, $statuses[tag_manager::SYNC_STATUS_NEEDS_SYNC]->statuscount);
    }

    /**
     * Provides sync statuses to tests
     * @return array
     */
    public static function sync_status_provider(): array {
        $tests = [];
        foreach (tag_manager::SYNC_STATUSES as $status) {
            $tests[$status] = [
                'status' => $status,
            ];
        }
        return $tests;
    }

    /**
     * Tests get_sync_status_string
     * @param int $status
     * @dataProvider sync_status_provider
     * @covers \tool_objectfs\local\tag_manager::get_sync_status_string
     */
    public function test_get_sync_status_string(int $status) {
        $string = tag_manager::get_sync_status_string($status);
        // Cheap check to ensure placeholder string not returned.
        $this->assertStringNotContainsString('[', $string);
    }

    /**
     * Tests get_sync_status_string when an invalid status is provided
     * @covers \tool_objectfs\local\tag_manager::get_sync_status_string
     */
    public function test_get_sync_status_string_does_not_exist() {
        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage('No status string is mapped for status: 5');
        tag_manager::get_sync_status_string(5);
    }

    /**
     * Tests the length of the defined tag source is checked correctly
     * @covers \tool_objectfs\local\environment_source
     */
    public function test_environment_source_too_long() {
        global $CFG;
        set_config('taggingenvironment', 'This is a really long string.
            It needs to be long because it needs to be more than 128 chars for the test to trigger an exception.',
            'tool_objectfs');

        $source = new environment_source();

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('tagsource:environment:toolong', 'tool_objectfs'));
        $source->get_value_for_contenthash('test');
    }
}
