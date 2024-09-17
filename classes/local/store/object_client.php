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

namespace tool_objectfs\local\store;

/**
 * Objectfs client interface.
 *
 * @package   tool_objectfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface object_client {
    /**
     * construct
     * @param \stdClass $config
     */
    public function __construct($config);

    /**
     * register_stream_wrapper
     * @return mixed
     */
    public function register_stream_wrapper();

    /**
     * get_fullpath_from_hash
     * @param string $contenthash
     *
     * @return string
     */
    public function get_fullpath_from_hash($contenthash);

    /**
     * delete_file
     * @param string $fullpath
     *
     * @return mixed
     */
    public function delete_file($fullpath);

    /**
     * rename_file
     * @param string $currentpath
     * @param string $destinationpath
     *
     * @return mixed
     */
    public function rename_file($currentpath, $destinationpath);

    /**
     * get_seekable_stream_context
     * @return mixed
     */
    public function get_seekable_stream_context();

    /**
     * get_availability
     * @return mixed
     */
    public function get_availability();

    /**
     * get_maximum_upload_size
     * @return mixed
     */
    public function get_maximum_upload_size();

    /**
     * verify_object
     * @param string $contenthash
     * @param string $localpath
     *
     * @return mixed
     */
    public function verify_object($contenthash, $localpath);

    /**
     * generate_presigned_url
     * @param string $contenthash
     * @param array $headers
     *
     * @return mixed
     */
    public function generate_presigned_url($contenthash, $headers = []);

    /**
     * support_presigned_urls
     * @return mixed
     */
    public function support_presigned_urls();

    /**
     * test_connection
     * @return mixed
     */
    public function test_connection();

    /**
     * test_permissions
     * @param mixed $testdelete
     *
     * @return mixed
     */
    public function test_permissions($testdelete);

    /**
     * proxy_range_request
     * @param \stored_file $file
     * @param mixed $ranges
     *
     * @return mixed
     */
    public function proxy_range_request(\stored_file $file, $ranges);

    /**
     * test_range_request
     * @param mixed $filesystem
     *
     * @return mixed
     */
    public function test_range_request($filesystem);

    /**
     * Get the expiry time of the token used for this fs.
     * returns -1 if not implemented, or no token is set.
     * @return int unix timestamp the token set expires at
     */
    public function get_token_expiry_time(): int;
}


