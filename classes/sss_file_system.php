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

require_once($CFG->dirroot . '/admin/tool/sssfs/lib.php');

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
     * @param object $client s3 client
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
        $filepath = $this->get_fullpath_from_hash($contenthash);
        $this->ensure_readable_by_hash($contenthash);
        return unlink($filepath);
    }

    public function copy_file_from_sss_to_local($contenthash) {
        $localfilepath = $filepath = $this->get_fullpath_from_hash($contenthash, true);
        $sssfilepath = $this->sssclient->get_sss_fullpath_from_contenthash($contenthash);
        return copy($sssfilepath, $localfilepath);
    }

    public function copy_file_from_local_to_sss($contenthash) {
        $this->ensure_readable_by_hash($contenthash);
        $localfilepath = $filepath = $this->get_fullpath_from_hash($contenthash, true);
        $sssfilepath = $this->sssclient->get_sss_fullpath_from_contenthash($contenthash);
        return copy($localfilepath, $sssfilepath);
    }

    public function get_local_md5_from_contenthash($contenthash) {
        $localfilepath = $this->get_fullpath_from_hash($contenthash, true);
        $md5 = md5_file($localfilepath);
        return $md5;
    }


    protected function is_hash_in_sss($contenthash) {
        global $DB;
        $location = $DB->get_field('tool_sssfs_filestate', 'location', array('contenthash' => $contenthash));
        $ssslocations = array(SSS_FILE_LOCATION_DUPLICATED, SSS_FILE_LOCATION_EXTERNAL);
        if ($location && in_array($location, $ssslocations)) {
            return true;
        }
    }

    /**
     * Get the full directory to the stored file, including the path to the
     * filedir, and the directory which the file is actually in.
     *
     * @param string $contenthash The content hash
     * @return string The full path to the content directory
     */
    protected function get_fulldir_from_hash($contenthash, $forcelocal = false) {
        if ($forcelocal || !$this->is_hash_in_sss($contenthash)) {
            return $this->filedir . DIRECTORY_SEPARATOR . $this->get_contentdir_from_hash($contenthash);
        }
        return $this->sssclient->get_fulldir();
    }

    /**
     * Get the full path for the specified hash, including the path to the filedir.
     *
     * @param string $contenthash The content hash
     * @return string The full path to the content file
     */
    protected function get_fullpath_from_hash($contenthash, $forcelocal = false) {
        if ($forcelocal || !$this->is_hash_in_sss($contenthash)) {
            return $this->filedir . DIRECTORY_SEPARATOR . $this->get_contentpath_from_hash($contenthash);
        }
        return $this->sssclient->get_fullpath_from_hash($contenthash);
    }

}