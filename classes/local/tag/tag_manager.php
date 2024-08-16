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

namespace tool_objectfs\local\tag;

use coding_exception;
use html_table;
use html_writer;
use tool_objectfs\local\manager;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/admin/tool/objectfs/lib.php');

/**
 * Manages object tagging feature.
 *
 * @package   tool_objectfs
 * @author    Matthew Hilton <matthewhilton@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tag_manager {

    /**
     * @var int If object needs sync. These will periodically be picked up by scheduled tasks and queued for syncing.
     */
    public const SYNC_STATUS_NEEDS_SYNC = 0;

    /**
     * @var int Object does not need sync. Will be essentially ignored in tagging process.
     */
    public const SYNC_STATUS_COMPLETE = 1;

    /**
     * @var int Object tried to sync but there was an error. Will make it ignored and must be corrected manually.
     */
    public const SYNC_STATUS_ERROR = 2;

    /**
     * @var array All possible tag sync statuses.
     */
    public const SYNC_STATUSES = [
        self::SYNC_STATUS_NEEDS_SYNC,
        self::SYNC_STATUS_COMPLETE,
        self::SYNC_STATUS_ERROR,
    ];

    /**
     * Returns an array of tag_source instances that are currently defined.
     * @return array
     */
    public static function get_defined_tag_sources(): array {
        // All possible tag sources should be defined here.
        // Note this should be a maximum of 10 sources, as this is an AWS limit.
        return [
            new mime_type_source(),
            new environment_source(),
        ];
    }

    /**
     * Is the tagging feature enabled and supported by the configured fs?
     * @return bool
     */
    public static function is_tagging_enabled_and_supported(): bool {
        $enabledinconfig = !empty(get_config('tool_objectfs', 'taggingenabled'));

        $client = manager::get_client(manager::get_objectfs_config());
        $supportedbyfs = !empty($client) && $client->supports_object_tagging();

        return $enabledinconfig && $supportedbyfs;
    }

    /**
     * Gathers the tag values for a given content hash
     * @param string $contenthash
     * @return array array of key=>value pairs, the tags for the given file.
     */
    public static function gather_object_tags_for_upload(string $contenthash): array {
        $tags = [];
        foreach (self::get_defined_tag_sources() as $source) {
            $val = $source->get_value_for_contenthash($contenthash);

            // Null means not set for this object.
            if (is_null($val)) {
                continue;
            }

            $tags[$source->get_identifier()] = $val;
        }
        return $tags;
    }

    /**
     * Stores tag records for contenthash locally
     * @param string $contenthash
     * @param array $tags
     */
    public static function store_tags_locally(string $contenthash, array $tags) {
        global $DB;

        // Purge any existing tags for this object.
        $DB->delete_records('tool_objectfs_object_tags', ['contenthash' => $contenthash]);

        // Store new records.
        $recordstostore = [];
        foreach ($tags as $key => $value) {
            $recordstostore[] = [
                'contenthash' => $contenthash,
                'tagkey' => $key,
                'tagvalue' => $value,
            ];
        }
        $DB->insert_records('tool_objectfs_object_tags', $recordstostore);
    }

    /**
     * Returns objects that are candidates for tag syncing.
     * @param int $limit max number of records to return
     * @return array array of contenthashes, which need tags calculated and synced.
     */
    public static function get_objects_needing_sync(int $limit) {
        global $DB;

        // Find object records where the status is NEEDS_SYNC and is replicated.
        [$insql, $inparams] = $DB->get_in_or_equal([
            OBJECT_LOCATION_DUPLICATED, OBJECT_LOCATION_EXTERNAL, OBJECT_LOCATION_ORPHANED], SQL_PARAMS_NAMED);
        $inparams['syncstatus'] = self::SYNC_STATUS_NEEDS_SYNC;
        $records = $DB->get_records_select('tool_objectfs_objects', 'tagsyncstatus = :syncstatus AND location ' . $insql,
            $inparams, '', 'contenthash', 0, $limit);
        return array_column($records, 'contenthash');
    }

    /**
     * Marks a given object as the given status.
     * @param string $contenthash
     * @param int $status one of SYNC_STATUS_* constants
     */
    public static function mark_object_tag_sync_status(string $contenthash, int $status) {
        global $DB;
        if (!in_array($status, self::SYNC_STATUSES)) {
            throw new coding_exception("Invalid object tag sync status " . $status);
        }
        $DB->set_field('tool_objectfs_objects', 'tagsyncstatus', $status, ['contenthash' => $contenthash]);
    }

    /**
     * Updates the tagslastpushed time for a given object.
     * This should only be done once successfully pushed to the external store.
     * @param string $contenthash
     * @param int $time time to set
     */
    public static function record_tag_pushed_time(string $contenthash, int $time) {
        global $DB;
        $DB->set_field('tool_objectfs_objects', 'tagslastpushed', $time, ['contenthash' => $contenthash]);
    }

    /**
     * Returns a simple list of all the sources and their descriptions.
     * @return string html string
     */
    public static function get_tag_source_summary_html(): string {
        $sources = self::get_defined_tag_sources();
        $table = new html_table();
        $table->head = [
            get_string('table:tagsource', 'tool_objectfs'),
            get_string('table:tagsourcemeaning', 'tool_objectfs'),
        ];

        foreach ($sources as $source) {
            $table->data[$source->get_identifier()] = [$source->get_identifier(), $source->get_description()];
        }
        return html_writer::table($table);
    }

    /**
     * If the current env is allowed to overwrite tags on objects that already have tags.
     * @return bool
     */
    public static function can_overwrite_object_tags(): bool {
        return (bool) get_config('tool_objectfs', 'overwriteobjecttags');
    }

    /**
     * Get the string for a given tag sync status
     * @param int $tagsyncstatus one of SYNC_STATUS_*
     * @return string
     */
    private static function get_sync_status_string(int $tagsyncstatus): string {
        $strmap = [
            self::SYNC_STATUS_ERROR => 'error',
            self::SYNC_STATUS_NEEDS_SYNC => 'needssync',
            self::SYNC_STATUS_COMPLETE => 'notrequired',
        ];

        if (!array_key_exists($tagsyncstatus, $strmap)) {
            throw new coding_exception('No status string is mapped for status: ' . $tagsyncstatus);
        }

        return get_string('tagsyncstatus:' . $strmap[$tagsyncstatus], 'tool_objectfs');
    }

    /**
     * Returns a html table with summaries of the sync statuses and the object count for each.
     * @return string
     */
    public static function get_tag_sync_status_summary_html(): string {
        global $DB;
        $statuses = $DB->get_records_sql("SELECT tagsyncstatus, COUNT(tagsyncstatus)
                                            FROM {tool_objectfs_objects}
                                        GROUP BY tagsyncstatus");

        $table = new html_table();
        $table->head = [
            get_string('table:status', 'tool_objectfs'),
            get_string('table:objectcount', 'tool_objectfs'),
        ];

        foreach (self::SYNC_STATUSES as $status) {
            // If no objects have a status, they won't appear in the SQL above.
            // In this case, just show zero (so the use knows it exists, but is zero).
            $count = isset($statuses[$status]) ? $statuses[$status]->count : 0;
            $table->data[$status] = [self::get_sync_status_string($status), $count];
        }
        return html_writer::table($table);
    }
}