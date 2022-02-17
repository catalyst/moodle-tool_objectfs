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
}


