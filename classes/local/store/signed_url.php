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
 * A signed URL for direct downloads
 * 
 * A signed URL which can be used by a user to directly download a file from object store, rather
 * than from the Moodle server.
 *
 * Signed URLs are valid for a limited time, indicated by the $expiresat value.
 *
 * This can be obtained using the object_client::generate_presigned_url function.
 *
 * @package tool_objectfs
 * @copyright 2022 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class signed_url {
    /** @var \moodle_url URL to redirect to */
    public $url;

    /** @var int Expiry timestamp (Unix epoch) after which this URL will stop working */
    public $expiresat;

    /**
     * construct
     * @param \moodle_url $url URL to redirect to
     * @param int $expiresat Expiry timestamp (Unix epoch) after which this URL will stop working
     */
    public function __construct($url, $expiresat) {
        $this->url = $url;
        $this->expiresat = $expiresat;
    }
}
