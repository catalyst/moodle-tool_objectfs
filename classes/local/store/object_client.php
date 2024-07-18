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
 * Objectfs client interface.
 *
 * @package   tool_objectfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\local\store;

use stdClass;

interface object_client {
    public function __construct($config);
    public function register_stream_wrapper();
    public function get_fullpath_from_hash($contenthash);
    public function delete_file($fullpath);
    public function rename_file($currentpath, $destinationpath);
    public function get_seekable_stream_context();
    public function get_availability();
    public function get_maximum_upload_size();
    public function verify_object($contenthash, $localpath);
    public function generate_presigned_url($contenthash, $headers = array());
    public function support_presigned_urls();
    public function test_connection();
    public function test_permissions($testdelete);
    public function proxy_range_request(\stored_file $file, $ranges);
    public function test_range_request($filesystem);

    /*
     * Tests setting an objects tag.
     * @return stdClass containing 'success' and 'details' properties
     */
    public function test_set_object_tag(): stdClass;

    /**
     * Set the given objects tags in the external store.
     * @param string $contenthash file content hash
     * @param array $tags array of key=>value pairs to set as tags.
     */
    public function set_object_tags(string $contenthash, array $tags);

    /**
     * Returns given objects tags queried from the external store. External object must exist.
     * @param string $contenthash file content has
     * @return array array of key=>value tag pairs
     */
    public function get_object_tags(string $contenthash): array;

    /**
     * If the client supports object tagging feature.
     * @return bool true if supports, else false
     */
    public function supports_object_tagging(): bool;
}


