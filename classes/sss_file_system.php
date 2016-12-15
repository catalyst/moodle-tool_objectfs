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
use core_files\filestorage\stored_file;
use core_files\filestorage\file_exception;
use tool_sssfs\sss_client;

defined('MOODLE_INTERNAL') || die();

class sss_file_system extends file_system {

    private $sssclient;

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
        $config = get_config('tool_sssfs');
        $sssclient = new sss_client($config);
        $this->set_sss_client($sssclient);
    }

    // We do this so we can inject a mocked one for unit testing.
    public function set_sss_client($client) {
        $this->sssclient = $client;
    }

    public function get_local_content_from_contenthash($contenthash) {
        $this->ensure_readable_by_hash($contenthash);
        $filepath = $this->get_fullpath_from_hash($contenthash);
        return file_get_contents($filepath);
    }

    public function delete_local_file_from_contenthash($contenthash) {
        $this->ensure_readable_by_hash($contenthash);
        $filepath = $this->get_fullpath_from_hash($contenthash);
        unlink($filepath);
    }

    private function get_sss_fullpath_from_file(stored_file $file) {
        $contenthash = $file->get_contenthash();
        $path = $this->sssclient->get_sss_fullpath_from_contenthash($contenthash);
        return $path;
    }

    public function readfile(stored_file $file) {
        $canreadlocal = $this->is_readable($file);
        if ($canreadlocal) {
            $path = $this->get_fullpath_from_storedfile($file, true);
        } else {
            $path = $this->get_sss_fullpath_from_file($file);
        }
        readfile_allow_large($path, $file->get_filesize());
    }

    public function get_content(stored_file $file) {
        $canreadlocal = $this->is_readable($file);
        if ($canreadlocal) {
            $path = $this->get_fullpath_from_storedfile($file, true);
        } else {
            $path = $this->get_sss_fullpath_from_file($file);
        }
        return file_get_contents($path);
    }

    public function get_content_file_handle($file, $type = stored_file::FILE_HANDLE_FOPEN) {
        $canreadlocal = $this->is_readable($file);
        if ($canreadlocal) {
            $path = $this->get_fullpath_from_storedfile($file, true);
        } else {
            $path = $this->get_sss_fullpath_from_file($file);
        }
        return self::get_file_handle_for_path($path, $type);
    }
}