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
 * File system for Openstack Object Storage
 *
 * @package    tool_objectfs
 * @author     Matt Clarkson
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs;

use tool_objectfs\local\store\swift\file_system;

/**
 * [Description swift_file_system]
 */
class swift_file_system extends file_system {
    /**
     * Output the content of the specified stored file.
     *
     * Note, this is different to get_content() as it uses the built-in php
     * readfile function which is more efficient.
     *
     * @param stored_file $file The file to serve.
     * @return void
     */
    public function readfile(\stored_file $file) {
        \core_php_time_limit::raise(HOURSECS);
        $path = $this->get_remote_path_from_storedfile($file);
        if (readfile($path) === false) {
            throw new \file_exception('storedfilecannotreadfile', $file->get_filename());
        }
    }
}
