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
 * @package   tool_objectfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/admin/tool/objectfs/lib.php');
require_once($CFG->libdir . '/filestorage/file_system_filedir.php');

class object_file_system extends \file_system_filedir {

    private $remoteclient;
    private $preferremote;

    public function __construct() {
        parent::__construct(); // Setup fildir.

        $config = get_objectfs_config();

        $this->remoteclient = $this->get_remote_client($config);
        $this->remoteclient->register_stream_wrapper();

        $this->preferremote = $config['preferremote'];
    }

    private function get_remote_client($config) {
        global $CFG;
        if (isset($CFG->objectfs_remote_client_class)) {
            $clientclass = $CFG->objectfs_remote_client_class;
        } else {
            $clientclass = '\tool_objectfs\client\s3_client';
        }

        $remoteclient = new $clientclass($config);

        return $remoteclient;
    }

    protected function get_object_path_from_storedfile($file) {
        if ($this->preferremote) {
            $location = $this->get_actual_object_location_by_hash($file->get_contenthash());
            if ($location == OBJECT_LOCATION_DUPLICATED) {
                return $this->get_remote_path_from_storedfile($file);
            }
        }

        if ($this->is_file_readable_locally_by_storedfile($file)) {
            $path = $this->get_local_path_from_storedfile($file);
        } else {
            // We assume it is remote, not checking if it's readable.
            $path = $this->get_remote_path_from_storedfile($file);
        }

        return $path;
    }

    /**
     * Get the full path for the specified hash, including the path to the filedir.
     *
     * Note: This must return a consistent path for the file's contenthash
     * and the path _will_ be in a standard local format.
     * Streamable paths will not work.
     * A local copy of the file _will_ be fetched if $fetchifnotfound is tree.
     *
     * The $fetchifnotfound allows you to determine the expected path of the file.
     *
     * @param string $contenthash The content hash
     * @param bool $fetchifnotfound Whether to attempt to fetch from the remote path if not found.
     * @return string The full path to the content file
     */
    protected function get_local_path_from_hash($contenthash, $fetchifnotfound = false) {
        $path = parent::get_local_path_from_hash($contenthash, $fetchifnotfound);

        if ($fetchifnotfound && !is_readable($path)) {
            $this->copy_object_from_remote_to_local_by_hash($contenthash);
        }

        return $path;
    }

    /**
     * Get a remote filepath for the specified stored file.
     *
     * This is typically either the same as the local filepath, or it is a streamable resource.
     *
     * See https://secure.php.net/manual/en/wrappers.php for further information on valid wrappers.
     *
     * @param stored_file $file The file to serve.
     * @return string full path to pool file with file content
     */
    protected function get_remote_path_from_storedfile(\stored_file $file) {
        return $this->get_remote_path_from_hash($file->get_contenthash());
    }

    /**
     * Get the full path for the specified hash, including the path to the filedir.
     *
     * This is typically either the same as the local filepath, or it is a streamable resource.
     *
     * See https://secure.php.net/manual/en/wrappers.php for further information on valid wrappers.
     *
     * @param string $contenthash The content hash
     * @return string The full path to the content file
     */
    protected function get_remote_path_from_hash($contenthash) {
        return $this->remoteclient->get_object_fullpath_from_hash($contenthash);
    }

    public function get_md5_from_contenthash($contenthash) {
        $localpath = $this->get_local_path_from_hash($contenthash);
        $remotepath = $this->get_remote_path_from_hash($contenthash);

        if (is_readable($localpath)) {
            $md5 = md5_file($localpath);
        } else {
            $md5 = $this->remoteclient->get_object_md5_from_key($contenthash);
        }
        return $md5;
    }

    public function get_actual_object_location_by_hash($contenthash) {
        $localpath = $this->get_local_path_from_hash($contenthash);
        $remotepath = $this->get_remote_path_from_hash($contenthash);

        $localreadable = is_readable($localpath);
        $remotereadable = is_readable($remotepath);

        if ($localreadable && $remotereadable) {
            return OBJECT_LOCATION_DUPLICATED;
        } else if ($localreadable && !$remotereadable) {
            return OBJECT_LOCATION_LOCAL;
        } else if (!$localreadable && $remotereadable) {
            return OBJECT_LOCATION_REMOTE;
        } else {
            return OBJECT_LOCATION_ERROR;
        }
    }

    public function copy_object_from_remote_to_local_by_hash($contenthash) {
        $localpath = $this->get_local_path_from_hash($contenthash);
        $remotepath = $this->get_remote_path_from_hash($contenthash);
        if (is_readable($remotepath) && !is_readable($localpath)) {
            //TODO: lock this up.
            return copy($remotepath, $localpath);
        }
        return false;
    }

    public function copy_object_from_local_to_remote_by_hash($contenthash) {
        $localpath = $this->get_local_path_from_hash($contenthash);
        $remotepath = $this->get_remote_path_from_hash($contenthash);
        if (is_readable($localpath) && !is_readable($remotepath)) {
            //TODO: lock this up.
            return copy($localpath, $remotepath);
        }
        return false;
    }

    public function delete_object_from_local_by_hash($contenthash) {
        $localpath = $this->get_local_path_from_hash($contenthash);
        $remotepath = $this->get_remote_path_from_hash($contenthash);

        // We want to be very sure it is remote if we're deleting objects.
        // There is no going back.
        if (is_readable($localpath) && is_readable($remotepath)) {
            return unlink($localpath);
        }
        return false;
    }

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
        $path = $this->get_object_path_from_storedfile($file);
        readfile_allow_large($path, $file->get_filesize());
    }

    /**
     * Get the content of the specified stored file.
     *
     * Generally you will probably want to use readfile() to serve content,
     * and where possible you should see if you can use
     * get_content_file_handle and work with the file stream instead.
     *
     * @param stored_file $file The file to retrieve
     * @return string The full file content
     */
    public function get_content(\stored_file $file) {
        if (!$file->get_filesize()) {
            // Directories are empty. Empty files are not worth fetching.
            return '';
        }

        $path = $this->get_object_path_from_storedfile($file);
        return file_get_contents($path);
    }

    /**
     * Serve file content using X-Sendfile header.
     * Please make sure that all headers are already sent and the all
     * access control checks passed.
     *
     * @param string $contenthash The content hash of the file to be served
     * @return bool success
     */
    public function xsendfile($contenthash) {
        global $CFG;
        require_once($CFG->libdir . "/xsendfilelib.php");

        $path = $this->get_object_path_from_storedfile($file);
        return xsendfile($path);
    }

    /**
     * Returns file handle - read only mode, no writing allowed into pool files!
     *
     * When you want to modify a file, create a new file and delete the old one.
     *
     * @param stored_file $file The file to retrieve a handle for
     * @param int $type Type of file handle (FILE_HANDLE_xx constant)
     * @return resource file handle
     */
    public function get_content_file_handle(\stored_file $file, $type = \stored_file::FILE_HANDLE_FOPEN) {
        // Most object repo streams do not support gzopen.
        if ($type == \stored_file::FILE_HANDLE_GZOPEN) {
            $path = $this->get_local_path_from_storedfile($file, true);
        } else {
            $path = $this->get_object_path_from_storedfile($file);
        }
        return self::get_file_handle_for_path($path, $type);
    }

    /**
     * Marks pool file as candidate for deleting.
     *
     * We adjust this method from the parent to never delete remote objects
     *
     * @param string $contenthash
     */
    public function remove_file($contenthash) {
        if (!self::is_file_removable($contenthash)) {
            // Don't remove the file - it's still in use.
            return;
        }

        if ($this->is_file_readable_remotely_by_hash($contenthash)) {
            // We never delete remote objects.
            return;
        }

        if (!$this->is_file_readable_locally_by_hash($contenthash)) {
            // The file wasn't found in the first place. Just ignore it.
            return;
        }

        $trashpath  = $this->get_trash_fulldir_from_hash($contenthash);
        $trashfile  = $this->get_trash_fullpath_from_hash($contenthash);
        $contentfile = $this->get_local_path_from_hash($contenthash);

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

        // Fix permissions, only if needed.
        $currentperms = octdec(substr(decoct(fileperms($trashfile)), -4));
        if ((int)$this->filepermissions !== $currentperms) {
            chmod($trashfile, $this->filepermissions);
        }
    }

}
