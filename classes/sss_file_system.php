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
 *
 * @package   tool_sssfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_sssfs;

use core_files\filestorage\file_system;
use core_files\filestorage\file_storage;

defined('MOODLE_INTERNAL') || die();

class sss_file_system extends file_system {

    /**
     * Constructor.
     *
     * Please use file_system::instance() instead.
     *
     * @param string $filedir The path to the local filedir.
     * @param string $trashdir The path to the trashdir.
     * @param int $dirpermissions The directory permissions when creating new directories
     * @param int $filepermissions The file permissions when creating new files
     * @param file_storage $fs The instance of file_storage to instantiate the class with.
     */
    public function __construct($filedir, $trashdir, $dirpermissions, $filepermissions, file_storage $fs = null) {

        parent::__construct($filedir, $trashdir, $dirpermissions, $filepermissions, $fs);

    }

    // Does not check if it is readable.
    public function get_content_from_hash($contenthash) {
        $filepath = $this->get_fullpath_from_hash($contenthash);
        return file_get_contents($filepath);
    }

    public function get_content_hashes_over_threshold($threshold) {
        global $DB;
        $sql = "SELECT DISTINCT contenthash FROM {files} WHERE filesize > ?";
        $contenthashes = $DB->get_fieldset_sql($sql, array($threshold));
        return $contenthashes;
    }

}