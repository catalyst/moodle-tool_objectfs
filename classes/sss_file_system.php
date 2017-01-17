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
use core_files\filestorage\file_packer;
use core_files\filestorage\file_progress;
use core_files\filestorage\file_exception;
use tool_sssfs\sss_client;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/admin/tool/sssfs/lib.php');

class sss_file_system extends file_system {

    private $sssclient;
    private $prefersss;
    private $enabled;

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
        if (isset($config->enabled) && $config->enabled) {
            $sssclient = new sss_client($config);
            $this->set_sss_client($sssclient);
            $this->prefersss = $config->prefersss;
            $this->enabled = $config->enabled;
        } else {
            $this->enabled = false;
        }
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
     * get location of contenthash file from the
     * tool_sssfs_filestate table. if content hash is not in the table,
     * we assume it is stored locally or is to be stored locally.
     *
     * @param  string $contenthash files contenthash
     *
     * @return int contenthash file location.
     */
    protected function get_hash_location($contenthash) {
        global $DB;
        $location = $DB->get_field('tool_sssfs_filestate', 'location', array('contenthash' => $contenthash));

        if ($location) {
            return $location;
        }

        return SSS_FILE_LOCATION_LOCAL;
    }

    /**
     * Returns path to the file if it was in s3.
     * Does not check if it actually is there.
     *
     * @param  stored_file $file stored file record
     *
     * @return string s3 file path
     */
    protected function get_sss_fullpath_from_file(stored_file $file) {
        return $this->get_sss_fullpath_from_hash($file->get_contenthash());
    }

    /**
     * Returns path to the file if it was in s3 based on conenthash.
     * Does not check if it actually is there.
     *
     * @param  string $contenthash files contenthash
     *
     * @return string s3 file path
     */
    protected function get_sss_fullpath_from_hash($contenthash) {
        $path = $this->sssclient->get_sss_fullpath_from_hash($contenthash);
        return $path;
    }

    /**
     * Returns path to the file as if it was stored locally.
     * Does not check if it actually is there.
     *
     * Taken from get_fullpath_from_storedfile in parent class.
     *
     * @param  stored_file $file stored file record
     * @param  boolean     $sync sync external files.
     *
     * @return string local file path
     */
    protected function get_local_fullpath_from_file(stored_file $file, $sync = false) {
        if ($sync) {
            $file->sync_external_file();
        }
        return $this->get_local_fullpath_from_hash($file->get_contenthash());
    }

    /**
     * Returns path to the file as if it was stored locally from hash.
     * Does not check if it actually is there.
     *
     * Taken from get_fullpath_from_hash in parent class.
     *
     * @param  string $contenthash files contenthash
     *
     * @return string local file path
     */
    protected function get_local_fullpath_from_hash($contenthash) {
        return $this->filedir . DIRECTORY_SEPARATOR . $this->get_contentpath_from_hash($contenthash);
    }

    /**
     * Whether a file is readable locally. Will
     * try content recovery if not.
     *
     * Taken from is_readable in parent class.
     *
     * @param  stored_file $file stored file record
     *
     * @return boolean true if readable, false if not
     */
    protected function is_local_readable(stored_file $file) {
        $path = $this->get_local_fullpath_from_file($file, true);
        if (!is_readable($path)) {
            if (!$this->try_content_recovery($file) or !is_readable($path)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Whether a file is readable in s3.
     *
     * @param  stored_file $file stored file record
     *
     * @return boolean true if readable, false if not
     */
    protected function is_sss_readable($file) {
        $path = $this->get_sss_fullpath_from_file($file);
        return is_readable($path);
    }

    /**
     * Whether a file is readable anywhere.
     * Will check if it can read local, and if it cant,
     * it will try to read from s3.
     *
     * We dont just call is_readable_by_hash because following
     * precedent set by parent, we try content recovery for local
     * files here.
     *
     * @param  stored_file $file stored file record
     *
     * @return boolean true if readable, false if not
     */
    public function is_readable(stored_file $file) {
        // Must go at the start of every overridden method.
        if (!$this->enabled) {
            return parent::is_readable($file);
        }

        if ($this->is_local_readable($file) || $this->is_sss_readable($file)) {
            return true;
        }
        return false;
    }

    /**
     * Checks if a file is readable if it's path is local.
     *
     * @param  stored_file $file stored file record
     * @param  string $path file path
     *
     * @throws file_exception When the file could not be read locally.
     */
    protected function ensure_file_readable_if_local(stored_file $file, $path) {
        if ($this->sssclient->path_is_local($path) && !$this->is_local_readable($file)) {
            throw new file_exception('storedfilecannotread', '', $this->get_fullpath_from_storedfile($file));
        }
    }

    /**
     * Whether a file is readable anywhere by hash.
     * Will check if it can read local, and if it cant,
     * it will try to read from s3.
     *
     * Does not attempt content recovery if local.
     *
     * @param  string $contenthash files contenthash
     *
     * @return boolean true if readable, false if not
     */
    public function is_readable_by_hash($contenthash) {
        // Must go at the start of every overridden method.
        if (!$this->enabled) {
            return parent::is_readable_by_hash($file);
        }

        $isreadable = ($this->is_local_readable_by_hash($contenthash) || $this->is_sss_readable_by_hash($contenthash));
        return $isreadable;
    }

    /**
     * Checks if file is readable locally by hash.
     *
     * @param  string $contenthash files contenthash
     *
     * @return boolean true if readable, false if not
     */
    protected function is_local_readable_by_hash($contenthash) {
        $localpath  = $this->get_local_fullpath_from_hash($contenthash);
        return is_readable($localpath);
    }

    /**
     * Checks if file is readable in s3 by hash.
     *
     * @param  string $contenthash files contenthash
     *
     * @return boolean true if readable, false if not
     */
    protected function is_sss_readable_by_hash($contenthash) {
        $ssspath = $this->get_sss_fullpath_from_hash($contenthash);
        return is_readable($ssspath);
    }

    /**
     * Returns the fullpath for a given contenthash.
     * Queries the DB to determine file location and
     * then uses appropriate path function.
     *
     * @param  string $contenthash files contenthash
     *
     * @return string file path
     */
    protected function get_fullpath_from_hash($contenthash) {
        // Must go at the start of every overridden method.
        if (!$this->enabled) {
            return parent::get_fullpath_from_hash($contenthash);
        }

        $filelocation  = $this->get_hash_location($contenthash);

        switch ($filelocation) {
            case SSS_FILE_LOCATION_LOCAL:
                return $this->get_local_fullpath_from_hash($contenthash);
            case SSS_FILE_LOCATION_DUPLICATED:
                if ($this->prefersss) {
                    return $this->get_sss_fullpath_from_hash($contenthash);
                } else {
                    return $this->get_local_fullpath_from_hash($contenthash);
                }
            case SSS_FILE_LOCATION_EXTERNAL:
                return $this->get_sss_fullpath_from_hash($contenthash);
            default:
                return $this->get_local_fullpath_from_hash($contenthash);
        }
    }

    public function readfile(stored_file $file) {
        // Must go at the start of every overridden method.
        if (!$this->enabled) {
            return parent::readfile($file);
        }

        $path = $this->get_fullpath_from_storedfile($file, true);
        $this->ensure_file_readable_if_local($file, $path);
        readfile_allow_large($path, $file->get_filesize());
    }


    public function get_content(stored_file $file) {
        // Must go at the start of every overridden method.
        if (!$this->enabled) {
            return parent::get_content($file);
        }

        $path = $this->get_fullpath_from_storedfile($file, true);
        $this->ensure_file_readable_if_local($file, $path);
        return file_get_contents($path);

    }

    public function get_content_file_handle($file, $type = stored_file::FILE_HANDLE_FOPEN) {
        // Must go at the start of every overridden method.
        if (!$this->enabled) {
            return parent::get_content_file_handle($file, $type);
        }

        if ($type == stored_file::FILE_HANDLE_GZOPEN) {
            $this->pull_file_back_to_local_if_in_sss($file);
            $this->ensure_local_readable($file);
            // If prefersss is enabled we need to still read from local if duplicated.
            $path = $this->get_local_fullpath_from_file($file, true);
        } else {
            $path = $this->get_fullpath_from_storedfile($file, true);
            $this->ensure_file_readable_if_local($file, $path);
        }
        return self::get_file_handle_for_path($path, $type);
    }

    /**
     * Marks pool file as candidate for deleting.
     *
     * DO NOT call directly - reserved for core!!
     *
     * We dont delete S3 files.
     *
     * @param string $contenthash
     */
    public function deleted_file_cleanup($contenthash) {
        // Must go at the start of every overridden method.
        if (!$this->enabled) {
            return parent::deleted_file_cleanup($contenthash);
        }

        $localreadable = $this->is_local_readable_by_hash($contenthash);

        if (!$localreadable) {
            return; // Already deleted or in s3 which we dont want to delete.
        }

        $trashpath  = $this->get_trash_fulldir_from_hash($contenthash);
        $trashfile  = $this->get_trash_fullpath_from_hash($contenthash);
        $contentfile = $this->get_local_fullpath_from_hash($contenthash);

        if (!is_dir($trashpath)) {
            mkdir($trashpath, $this->dirpermissions, true);
        }

        if (file_exists($trashfile)) {
            // A copy of this file is already in the trash.
            // Remove the old version.
            unlink($contentfile);
            return;
        }

        // Move the contentfile to the trash, and fix permissions as required.
        rename($contentfile, $trashfile);
        chmod($trashfile, $this->filepermissions);
    }

    /**
     * Return mimetype by given file pathname.
     *
     * If file has a known extension, we return the mimetype based on extension.
     * Otherwise (when possible) we try to get the mimetype from file contents.
     *
     * @param string $fullpath Full path to the file on disk
     * @param string $filename Correct file name with extension, if omitted will be taken from $path
     * @return string
     */
    public static function mimetype($fullpath, $filename = null) {
        if (empty($filename)) {
            $filename = $fullpath;
        }

        // The mimeinfo function determines the mimetype purely based on the file extension.
        $type = mimeinfo('type', $filename);

        if ($type === 'document/unknown') {
            // The type is unknown. Inspect the file now.
            $type = self::mimetype_from_path($fullpath);
        }
        return $type;
    }

    /**
     * Inspect a file on disk for it's mimetype.
     * If it's in S3 we return document/unknown as finfo will not work.
     * Mimetype should be calculated on file creation, this is just a precaution.
     *
     * @param string $fullpath Path to file on disk
     * @param string $default The default mimetype to use if the file was not found.
     * @return string The mimetype
     */
    public static function mimetype_from_path($fullpath, $default = 'document/unknown') {
        $type = $default;

        $islocalpath = sss_client::path_is_local($fullpath);

        if ($islocalpath && file_exists($fullpath) && class_exists('finfo')) {
            // The type is unknown. Attempt to look up the file type now.
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            return mimeinfo_from_type('type', $finfo->file($fullpath));
        }

        return 'document/unknown';
    }

    /**
     * Inspect a file on disk for it's mimetype.
     *
     * @param string $contenthash The content hash of the file to query
     * @param string $default The default mimetype to use if the file was not found.
     * @return string The mimetype
     */
    public function mimetype_from_hash($contenthash, $filename) {
        $fullpath = $this->get_fullpath_from_hash($contenthash);
        return self::mimetype($fullpath, $filename);
    }

    /**
     * Retrieve the mime information for the specified stored file.
     *
     * @param stored_file $file The stored file to retrieve mime information for
     * @return string The MIME type.
     */
    public function mimetype_from_storedfile(stored_file $file) {
        $pathname = $this->get_fullpath_from_storedfile($file);
        $mimetype = self::mimetype($pathname, $file->get_filename());

        if (!$this->is_readable($file) && $mimetype === 'document/unknown') {
            // The type is unknown, but the full checks weren't completed because the file isn't locally available.
            // Ensure we have a local copy and try again.
            $this->ensure_readable($file);

            $mimetype = self::mimetype_from_path($pathname);
        }

        return $mimetype;
    }

    /**
     * Certain operations cannot be performed on s3 files.
     * use this to pull the file back as the before the operation is called.
     * It will be set back to duplicated and returned back to s3 later.
     *
     * @param  stored_file $file [description]
     *
     * @return [type]            [description]
     */
    protected function pull_file_back_to_local_if_in_sss(stored_file $file) {
        $path = $this->get_fullpath_from_storedfile($file, true);
        $islocal = $this->sssclient->path_is_local($path);

        if ($islocal) {
            return;
        }

        $contenthash = $file->get_contenthash();
        $timeout = 600; // 10 minutes before giving up.
        $locktype = 'tool_sssfs_file_manipulation';
        $resource = "contenthash: $contenthash";
        $lockfactory = \core\lock\lock_config::get_lock_factory($locktype);

        // We use a lock here incase this function is called twice in parallel.
        $lock = $lockfactory->get_lock($resource, $timeout);
        $localpath = $this->get_local_fullpath_from_hash($contenthash);

        $islocalreadable = is_readable($localpath); // Check its not local now.

        if (!$islocalreadable) {
            copy($path, $localpath);
            log_file_state($contenthash, SSS_FILE_LOCATION_DUPLICATED);
        }

        $lock->release();

    }

    /**
     * List contents of archive.
     *
     * @param stored_file $file The archive to inspect
     * @param file_packer $packer file packer instance
     * @return array of file infos
     */
    public function list_files($file, file_packer $packer) {
        $this->pull_file_back_to_local_if_in_sss($file);
        $this->ensure_local_readable($file);
        // If prefersss is enabled we need to still read from local if duplicated.
        $archivefile = $this->get_local_fullpath_from_file($file, true);
        return $packer->list_files($archivefile);
    }

    /**
     * Extract file to given file path (real OS filesystem), existing files are overwritten.
     *
     * @param stored_file $file The archive to inspect
     * @param file_packer $packer File packer instance
     * @param string $pathname Target directory
     * @param file_progress $progress progress indicator callback or null if not required
     * @return array|bool List of processed files; false if error
     */
    public function extract_to_pathname(stored_file $file, file_packer $packer, $pathname, file_progress $progress = null) {
        $this->pull_file_back_to_local_if_in_sss($file);
        $this->ensure_local_readable($file);
        // If prefersss is enabled we need to still read from local if duplicated.
        $archivefile = $this->get_local_fullpath_from_file($file, true);
        return $packer->extract_to_pathname($archivefile, $pathname, null, $progress);
    }

    /**
     * Extract file to given file path (real OS filesystem), existing files are overwritten.
     *
     * @param stored_file $file The archive to inspect
     * @param file_packer $packer file packer instance
     * @param int $contextid context ID
     * @param string $component component
     * @param string $filearea file area
     * @param int $itemid item ID
     * @param string $pathbase path base
     * @param int $userid user ID
     * @param file_progress $progress Progress indicator callback or null if not required
     * @return array|bool list of processed files; false if error
     */
    public function extract_to_storage(stored_file $file, file_packer $packer, $contextid,
            $component, $filearea, $itemid, $pathbase, $userid = null, file_progress $progress = null) {

        $this->pull_file_back_to_local_if_in_sss($file);
        // The extract_to_storage function needs the file to exist on disk.
        $this->ensure_local_readable($file);
        // If prefersss is enabled we need to still read from local if duplicated.
        $archivefile = $this->get_local_fullpath_from_file($file, true);
        return $packer->extract_to_storage($archivefile, $contextid,
                $component, $filearea, $itemid, $pathbase, $userid, $progress);
    }

    protected function ensure_local_readable($file) {
        if (!$this->is_local_readable($file)) {
            throw new file_exception('storedfilecannotread', '', $this->get_fullpath_from_storedfile($file));
        }
        return true;
    }

}