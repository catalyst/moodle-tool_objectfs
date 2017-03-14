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
 * object_file_system abstract class.
 *
 * Remote object storage providers extent this class.
 * At minimum you need to impletment get_remote_client.
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

abstract class object_file_system extends \file_system_filedir {

    private $remoteclient;
    private $preferremote;
    private $logger;

    public function __construct() {
        parent::__construct(); // Setup fildir.

        $config = get_objectfs_config();

        $this->remoteclient = $this->get_remote_client($config);
        $this->remoteclient->register_stream_wrapper();
        $this->preferremote = $config->preferremote;

        if ($config->enablelogging) {
            $this->logger = new \tool_objectfs\log\real_time_logger();
        } else {
            $this->logger = new \tool_objectfs\log\null_logger();
        }
    }

    public function set_logger(\tool_objectfs\log\objectfs_logger $logger) {
        $this->logger = $logger;
    }

    protected abstract function get_remote_client($config);

    protected function get_object_path_from_storedfile($file) {
        $contenthash = $file->get_contenthash();
        return $this->get_object_path_from_hash($contenthash);
    }

    protected function get_object_path_from_hash($contenthash) {
        if ($this->preferremote) {
            $location = $this->get_actual_object_location_by_hash($contenthash);
            if ($location == OBJECT_LOCATION_DUPLICATED) {
                return $this->get_remote_path_from_hash($contenthash);
            }
        }

        if ($this->is_file_readable_locally_by_hash($contenthash)) {
            $path = $this->get_local_path_from_hash($contenthash);
        } else {
            // We assume it is remote, not checking if it's readable.
            $path = $this->get_remote_path_from_hash($contenthash);
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

            // Try and pull from remote.
            $objectlock = $this->acquire_object_lock($contenthash);

            // While gaining lock object might have been moved locally so we recheck.
            if ($objectlock && !is_readable($path)) {
                $location = $this->copy_object_from_remote_to_local_by_hash($contenthash);
                // We want this file to be deleted again later.
                update_object_record($contenthash, $location);

                $objectlock->release();
            }
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
        return $this->remoteclient->get_remote_fullpath_from_hash($contenthash);
    }

    public function get_actual_object_location_by_hash($contenthash) {
        $localreadable = $this->is_file_readable_locally_by_hash($contenthash);
        $remotereadable = $this->is_file_readable_remotely_by_hash($contenthash);

        if ($localreadable && $remotereadable) {
            return OBJECT_LOCATION_DUPLICATED;
        } else if ($localreadable && !$remotereadable) {
            return OBJECT_LOCATION_LOCAL;
        } else if (!$localreadable && $remotereadable) {
            return OBJECT_LOCATION_REMOTE;
        } else {
            // Object is not anywhere - we toggle an error state in the DB.
            update_object_record($contenthash, OBJECT_LOCATION_ERROR);
            return OBJECT_LOCATION_ERROR;
        }
    }

    // Acquire the obect lock any time you are moving an object between locations.
    public function acquire_object_lock($contenthash) {
        $timeout = 600; // 10 minutes before giving up.
        $resource = "object: $contenthash";
        $lockfactory = \core\lock\lock_config::get_lock_factory('tool_objectfs_object');
        $lock = $lockfactory->get_lock($resource, $timeout);
        return $lock;
    }

    public function copy_object_from_remote_to_local_by_hash($contenthash, $objectsize = 0) {
        $initiallocation = $this->get_actual_object_location_by_hash($contenthash);
        $finallocation = $initiallocation;

        if ($initiallocation === OBJECT_LOCATION_REMOTE) {

            $localpath = $this->get_local_path_from_hash($contenthash);
            $remotepath = $this->get_remote_path_from_hash($contenthash);

            $localdirpath = $this->get_fulldir_from_hash($contenthash);

            // Folder may not exist yet if pulling a file that came from another environment.
            if (!is_dir($localdirpath)) {
                if (!mkdir($localdirpath, $this->dirpermissions, true)) {
                    // Permission trouble.
                    throw new file_exception('storedfilecannotcreatefiledirs');
                }
            }

            $success = copy($remotepath, $localpath);

            if ($success) {
                $finallocation = OBJECT_LOCATION_DUPLICATED;
            }

        }
        $this->logger->log_object_move('copy_object_from_remote_to_local',
                                        $initiallocation,
                                        $finallocation,
                                        $contenthash,
                                        $objectsize);
        return $finallocation;
    }

    public function copy_object_from_local_to_remote_by_hash($contenthash, $objectsize = 0) {
        $initiallocation = $this->get_actual_object_location_by_hash($contenthash);
        $finallocation = $initiallocation;

        if ($initiallocation === OBJECT_LOCATION_LOCAL) {

            $localpath = $this->get_local_path_from_hash($contenthash);
            $remotepath = $this->get_remote_path_from_hash($contenthash);

            $success = copy($localpath, $remotepath);

            if ($success) {
                $finallocation = OBJECT_LOCATION_DUPLICATED;
            }
        }

        $this->logger->log_object_move('copy_object_from_local_to_remote',
                                        $initiallocation,
                                        $finallocation,
                                        $contenthash,
                                        $objectsize);
        return $finallocation;
    }

    public function verify_remote_object_from_hash($contenthash) {
        $localpath = $this->get_local_path_from_hash($contenthash);
        $objectisvalid = $this->remoteclient->verify_remote_object($contenthash, $localpath);
        return $objectisvalid;
    }

    public function delete_object_from_local_by_hash($contenthash, $objectsize = 0) {
        $initiallocation = $this->get_actual_object_location_by_hash($contenthash);
        $finallocation = $initiallocation;

        if ($initiallocation === OBJECT_LOCATION_DUPLICATED) {
            $localpath = $this->get_local_path_from_hash($contenthash);

            if ($this->verify_remote_object_from_hash($contenthash)) {
                $success = unlink($localpath);

                if ($success) {
                    $finallocation = OBJECT_LOCATION_REMOTE;
                }
            }
        }

        $this->logger->log_object_move('copy_object_from_local_to_remote',
                                        $initiallocation,
                                        $finallocation,
                                        $contenthash,
                                        $objectsize);
        return $finallocation;
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

        $this->logger->start_timing();
        $success = readfile_allow_large($path, $file->get_filesize());
        $this->logger->end_timing();

        $this->logger->log_object_read('readfile', $path, $file->get_filesize());

        if (!$success) {
            update_object_record($file->get_contenthash(), OBJECT_LOCATION_ERROR);
        }
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

        $this->logger->start_timing();
        $contents = file_get_contents($path);
        $this->logger->end_timing();

        $this->logger->log_object_read('file_get_contents', $path, $file->get_filesize());

        if (!$contents) {
            update_object_record($file->get_contenthash(), OBJECT_LOCATION_ERROR);
        }

        return $contents;
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

        $path = $this->get_object_path_from_hash($contenthash);

        $this->logger->start_timing();
        $success = xsendfile($path);
        $this->logger->end_timing();

        $this->logger->log_object_read('xsendfile', $path);

        if (!$success) {
            update_object_record($contenthash, OBJECT_LOCATION_ERROR);
        }

        return $success;
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

        $this->logger->start_timing();
        $filehandle = self::get_file_handle_for_path($path, $type);
        $this->logger->end_timing();

        $this->logger->log_object_read('get_file_handle_for_path', $path, $file->get_filesize());

        if (!$filehandle) {
            update_object_record($file->get_contenthash(), OBJECT_LOCATION_ERROR);
        }

        return $filehandle;
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
