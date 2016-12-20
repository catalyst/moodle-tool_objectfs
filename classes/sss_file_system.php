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
     * sss_file_system Constructor.
     *
     * Calls file_system contructor and sets S3 client.
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

    /**
     * Sets s3 client.
     *
     * We have this so we can inject a mocked one for unit testing.
     *
     * @param [type] $client [description]
     */
    public function set_sss_client($client) {
        $this->sssclient = $client;
    }

    /**
     * Deletes a local file from it's contenthash.
     *
     * @param  string $contenthash content hash
     * @throws file_exception
     */
    public function delete_local_file_from_contenthash($contenthash) {
        $this->ensure_readable_by_hash($contenthash);
        $filepath = $this->get_fullpath_from_hash($contenthash);
        return unlink($filepath);
    }

    public function copy_file_from_sss_to_local($contenthash) {
        $localfilepath = $filepath = $this->get_fullpath_from_hash($contenthash);
        $sssfilepath = $this->sssclient->get_sss_fullpath_from_contenthash($contenthash);
        return copy($sssfilepath, $localfilepath);
    }

    public function copy_file_from_local_to_sss($contenthash) {
        $this->ensure_readable_by_hash($contenthash);
        $localfilepath = $filepath = $this->get_fullpath_from_hash($contenthash);
        $sssfilepath = $this->sssclient->get_sss_fullpath_from_contenthash($contenthash);
        return copy($localfilepath, $sssfilepath);
    }

    public function get_local_md5_from_contenthash($contenthash) {
        $localfilepath = $this->get_fullpath_from_hash($contenthash);
        $md5 = md5_file($localfilepath);
        return $md5;
    }

    /**
     * Returns S3 path.
     *
     * @param  stored_file $file stored file
     * @return string S3 file path
     */
    private function get_sss_fullpath_from_file(stored_file $file) {
        $contenthash = $file->get_contenthash();
        $path = $this->sssclient->get_sss_fullpath_from_contenthash($contenthash);
        return $path;
    }

    /**
     * Output the content of the specified stored file.
     *
     * Note, this is different to get_content() as it uses the built-in php
     * readfile function which is more efficient.
     *
     * If it cannot read a file locally, it tries to read from S3.
     *
     * @param stored_file $file The file to serve.
     * @throws file_exception
     * @throws S3Exceptions
     */
    public function readfile(stored_file $file) {
        $canreadlocal = $this->is_readable($file);
        if ($canreadlocal) {
            $path = $this->get_fullpath_from_storedfile($file, true);
        } else {
            $path = $this->get_sss_fullpath_from_file($file);
        }
        readfile_allow_large($path, $file->get_filesize());
    }

    /**
     * Get the content of the specified stored file.
     *
     * Generally you will probably want to use readfile() to serve content,
     * and where possible you should see if you can use
     * get_content_file_handle and work with the file stream instead.
     *
     * If it cannot read a file locally, it tries to read from S3.
     *
     * @param stored_file $file The file to retrieve
     * @return string The full file content
     * @throws file_exception
     * @throws S3Exceptions
     */
    public function get_content(stored_file $file) {
        $canreadlocal = $this->is_readable($file);
        if ($canreadlocal) {
            $path = $this->get_fullpath_from_storedfile($file, true);
        } else {
            $path = $this->get_sss_fullpath_from_file($file);
        }
        return file_get_contents($path);
    }

    /**
     * Returns file handle - read only mode, no writing allowed into pool files!
     *
     * When you want to modify a file, create a new file and delete the old one.
     *
     * @param stored_file $file The file to retrieve a handle for
     * @param int $type Type of file handle (FILE_HANDLE_xx constant)
     * @return resource file handle
     * @throws file_exception
     * @throws S3Exceptions
     */
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