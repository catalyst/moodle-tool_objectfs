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

namespace tool_objectfs\local\store;

use Exception;
use ParentIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use stored_file;
use file_storage;
use BlobRestProxy;
use tool_objectfs\local\manager;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/admin/tool/objectfs/lib.php');
require_once($CFG->libdir . '/filestorage/file_system.php');
require_once($CFG->libdir . '/filestorage/file_system_filedir.php');
require_once($CFG->libdir . '/filestorage/file_storage.php');

abstract class object_file_system extends \file_system_filedir {

    public $externalclient;
    private $preferexternal;
    private $deleteexternally;
    private $logger;

    public function __construct() {
        global $CFG;
        parent::__construct(); // Setup filedir.

        $config = manager::get_objectfs_config();

        $this->externalclient = $this->initialise_external_client($config);
        $this->externalclient->register_stream_wrapper();
        $this->preferexternal = $config->preferexternal;
        $this->filepermissions = $CFG->filepermissions;
        $this->dirpermissions = $CFG->directorypermissions;
        $this->deleteexternally = $config->deleteexternal;

        if ($config->enablelogging) {
            $this->set_logger(new \tool_objectfs\log\real_time_logger());
        } else {
            $this->set_logger(new \tool_objectfs\log\null_logger());
        }
    }

    public function set_logger(\tool_objectfs\log\objectfs_logger $logger) {
        $this->logger = $logger;
    }

    /**
     * Return logger.
     *
     * @return \tool_objectfs\log\objectfs_logger
     */
    public function get_logger() {
        return $this->logger;
    }

    /**
     * Return external client.
     *
     * @return \tool_objectfs\client\object_client
     */
    public function get_external_client() {
        return $this->externalclient;
    }

    abstract protected function initialise_external_client($config);

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
            $objectlock = $this->acquire_object_lock($contenthash, 600);

            // While gaining lock object might have been moved locally so we recheck.
            if ($objectlock && !is_readable($path)) {
                $location = $this->copy_object_from_external_to_local_by_hash($contenthash);
                // We want this file to be deleted again later.
                manager::update_object_by_hash($contenthash, $location);

            }
            if ($objectlock) {
                $objectlock->release();
            }
        }

        return $path;
    }

    public function get_remote_path_from_storedfile(\stored_file $file) {
        return $this->get_remote_path_from_hash($file->get_contenthash());
    }

    protected function get_remote_path_from_hash($contenthash) {
        if ($this->preferexternal) {
            $location = $this->get_object_location_from_hash($contenthash);
            if ($location == OBJECT_LOCATION_DUPLICATED) {
                return $this->get_external_path_from_hash($contenthash);
            }
        }

        if ($this->is_file_readable_locally_by_hash($contenthash)) {
            $path = $this->get_local_path_from_hash($contenthash);
        } else {
            // We assume it is remote, not checking if it's readable.
            $path = $this->get_external_path_from_hash($contenthash);
        }

        return $path;
    }

    protected function get_external_path_from_hash($contenthash) {
        return $this->externalclient->get_fullpath_from_hash($contenthash);
    }

    protected function get_external_path_from_storedfile(\stored_file $file) {
        return $this->get_external_path_from_hash($file->get_contenthash());
    }

    public function is_file_readable_externally_by_storedfile(stored_file $file) {
        if (!$file->get_filesize()) {
            // Files with empty size are either directories or empty.
            // We handle these virtually.
            return true;
        }

        $path = $this->get_external_path_from_storedfile($file);
        if (is_readable($path)) {
            return true;
        }

        return false;
    }

    public function is_file_readable_externally_by_hash($contenthash) {
        if ($contenthash === sha1('')) {
            // Files with empty size are either directories or empty.
            // We handle these virtually.
            return true;
        }

        $path = $this->get_external_path_from_hash($contenthash, false);

        // Note - it is not possible to perform a content recovery safely from a hash alone.
        return is_readable($path);
    }

    public function get_object_location_from_hash($contenthash) {
        $localreadable = $this->is_file_readable_locally_by_hash($contenthash);
        $externalreadable = $this->is_file_readable_externally_by_hash($contenthash);

        if ($localreadable && $externalreadable) {
            return OBJECT_LOCATION_DUPLICATED;
        } else if ($localreadable && !$externalreadable) {
            return OBJECT_LOCATION_LOCAL;
        } else if (!$localreadable && $externalreadable) {
            return OBJECT_LOCATION_EXTERNAL;
        } else {
            // Object is not anywhere - we toggle an error state in the DB.
            manager::update_object_by_hash($contenthash, OBJECT_LOCATION_ERROR);
            return OBJECT_LOCATION_ERROR;
        }
    }

    // Acquire the object lock any time you are moving an object between locations.
    public function acquire_object_lock($contenthash, $timeout = 0) {
        $resource = "tool_objectfs: $contenthash";
        $lockfactory = \core\lock\lock_config::get_lock_factory('tool_objectfs_object');
        $this->logger->start_timing();
        $lock = $lockfactory->get_lock($resource, $timeout);
        $this->logger->end_timing();
        $this->logger->log_lock_timing($lock);

        return $lock;
    }

    public function copy_object_from_external_to_local_by_hash($contenthash, $objectsize = 0) {
        $initiallocation = $this->get_object_location_from_hash($contenthash);
        $finallocation = $initiallocation;

        if ($initiallocation === OBJECT_LOCATION_EXTERNAL) {

            $localpath = $this->get_local_path_from_hash($contenthash);
            $localdirpath = $this->get_fulldir_from_hash($contenthash);

            // Folder may not exist yet if pulling a file that came from another environment.
            if (!is_dir($localdirpath)) {
                if (!mkdir($localdirpath, $this->dirpermissions, true)) {
                    // Permission trouble.
                    throw new file_exception('storedfilecannotcreatefiledirs');
                }
            }

            $success = $this->copy_from_external_to_local($contenthash);

            if ($success) {
                chmod($this->get_local_path_from_hash($contenthash), $this->filepermissions);
                $finallocation = OBJECT_LOCATION_DUPLICATED;
            }

        }
        $this->logger->log_object_move('copy_object_from_external_to_local',
                                        $initiallocation,
                                        $finallocation,
                                        $contenthash,
                                        $objectsize);
        return $finallocation;
    }

    public function copy_object_from_local_to_external_by_hash($contenthash, $objectsize = 0) {
        $initiallocation = $this->get_object_location_from_hash($contenthash);

        $finallocation = $initiallocation;

        if ($initiallocation === OBJECT_LOCATION_LOCAL) {

            $success = $this->copy_from_local_to_external($contenthash);

            if ($success) {
                $finallocation = OBJECT_LOCATION_DUPLICATED;
            }
        }

        $this->logger->log_object_move('copy_object_from_local_to_external',
                                        $initiallocation,
                                        $finallocation,
                                        $contenthash,
                                        $objectsize);
        return $finallocation;
    }

    public function verify_external_object_from_hash($contenthash) {
        $localpath = $this->get_local_path_from_hash($contenthash);
        $objectisvalid = $this->externalclient->verify_object($contenthash, $localpath);
        return $objectisvalid;
    }

    public function delete_object_from_local_by_hash($contenthash, $objectsize = 0) {
        $initiallocation = $this->get_object_location_from_hash($contenthash);
        $finallocation = $initiallocation;

        if ($initiallocation === OBJECT_LOCATION_DUPLICATED) {
            $localpath = $this->get_local_path_from_hash($contenthash);
            if ($this->verify_external_object_from_hash($contenthash)) {
                $success = unlink($localpath);

                if ($success) {
                    $finallocation = OBJECT_LOCATION_EXTERNAL;
                }
            }
        }

        $this->logger->log_object_move('delete_local_object',
                                        $initiallocation,
                                        $finallocation,
                                        $contenthash,
                                        $objectsize);
        return $finallocation;
    }

    /**
     * Recursively reads dirs from passed path and delete all empty dirs.
     * @param string $rootpath
     * @return bool
     */
    public function delete_empty_dirs($rootpath = '') {
        global $DB;

        $config = manager::get_objectfs_config();

        if (empty($rootpath)) {
            $rootpath = $this->filedir;
        }
        if (!is_dir($rootpath) || !is_writable($rootpath)) {
            return false;
        }
        $empty = true;
        foreach (glob($rootpath . DIRECTORY_SEPARATOR . '*') as $path) {
            if (is_file($path)) {
                // Check timemodified, don't touch anything more recent than 24 hours.
                $modified = filemtime($path);
                if ($modified === false) {
                    $modified = 0;
                }

                if ($modified <= (time() - DAYSECS)) {
                    $pathinfo = pathinfo($path);

                    // If there are .tmp files here, they should be killed.
                    if (!empty($pathinfo['extension']) && $pathinfo['extension'] === 'tmp') {
                        @unlink($path);
                    } else {
                        if (!$config->deletelocal) {
                            // If local objects aren't deleted, skip here.
                            // Or it will take ages to grind through for little benefit.
                            continue;
                        }

                        // If the file is 24hrs old, they may not be tracked by objectFS.
                        // This means it may not exist in the files table at all.
                        if ($config->sizethreshold > 0) {
                            // Don't care about files underneath the size threshold.
                            // Hurts performance for very little gain in space.
                            if (filesize($path) < $config->sizethreshold) {
                                continue;
                            }
                        }

                        // Check the basename and filename, should catch hidden files and other junk.
                        // Check pathnamehash as well. Should never happen, but any hash match should not be touched.
                        $sql = "SELECT *
                                  FROM {files}
                                 WHERE contenthash = ?
                                    OR contenthash = ?
                                    OR pathnamehash = ?
                                    OR pathnamehash = ?";
                        $exists = $DB->record_exists_sql($sql, [
                            $pathinfo['filename'],
                            $pathinfo['basename'],
                            $pathinfo['filename'],
                            $pathinfo['basename']
                        ]);

                        if (!$exists) {
                            @unlink($path);
                        }
                    }
                }
            }

            $empty &= is_dir($path) && $this->delete_empty_dirs($path);
        }
        if ($rootpath === $this->filedir) {
            return false;
        }
        if (!$empty) {
            return false;
        }
        return rmdir($rootpath);
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
        if (!$this->is_configured()) {
            parent::readfile($file);
        } else {
            $path = $this->get_remote_path_from_storedfile($file);

            $this->logger->start_timing();
            $success = readfile_allow_large($path, $file->get_filesize());
            $this->logger->end_timing();

            $this->logger->log_object_read('readfile', $path, $file->get_filesize());

            if (!$success) {
                manager::update_object_by_hash($file->get_contenthash(), OBJECT_LOCATION_ERROR);
            }
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

        $path = $this->get_remote_path_from_storedfile($file);

        $this->logger->start_timing();
        $contents = file_get_contents($path);
        $this->logger->end_timing();

        $this->logger->log_object_read('file_get_contents', $path, $file->get_filesize());

        if (!$contents) {
            manager::update_object_by_hash($file->get_contenthash(), OBJECT_LOCATION_ERROR);
        }

        return $contents;
    }

    /**
     * Serve file content using X-Sendfile header.
     * Please make sure that all headers are already sent and the all
     * access control checks passed.
     *
     * This alternate method to xsendfile() allows an alternate file system
     * to use the full file metadata and avoid extra lookups.
     *
     * @param stored_file $file The file to send
     * @return bool success
     * @throws \dml_exception
     * @throws \coding_exception
     */
    public function xsendfile_file(stored_file $file): bool {
        if (!$this->is_configured()) {
            return parent::xsendfile_file($file);
        }

        $contenthash = $file->get_contenthash();
        if ($this->presigned_url_configured() &&
                $this->presigned_url_should_redirect_file($file) &&
                $this->is_file_readable_externally_by_hash($contenthash)) {

            return $this->redirect_to_presigned_url($contenthash, headers_list());
        }

        $ranges = $this->get_valid_http_ranges($file->get_filesize());
        if ($this->externalclient->support_presigned_urls() && !empty($ranges) &&
                $this->is_file_readable_externally_by_hash($contenthash)) {

            return $this->externalclient->proxy_range_request($file, $ranges);
        }

        return false;
    }

    /**
     * Serve file content using X-Sendfile header.
     * Please make sure that all headers are already sent and the all
     * access control checks passed.
     *
     * Use this method to redirect to pre-signed URL if the file is readable externally.
     *
     * @param string $contenthash The content hash of the file to be served
     * @return bool success
     * @throws \dml_exception
     */
    public function xsendfile($contenthash) {
        if (!$this->is_configured()) {
            return parent::xsendfile($contenthash);
        }
        $headers = headers_list();
        if ($this->presigned_url_configured() &&
                $this->is_file_readable_externally_by_hash($contenthash) &&
                $this->presigned_url_should_redirect($contenthash, $headers)) {

            return $this->redirect_to_presigned_url($contenthash, $headers);
        }
        return false;
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
            $path = $this->get_remote_path_from_storedfile($file);
        }

        $this->logger->start_timing();
        $filehandle = $this->get_object_handle_for_path($path, $type);
        $this->logger->end_timing();

        $this->logger->log_object_read('get_file_handle_for_path', $path, $file->get_filesize());

        if (!$filehandle) {
            manager::update_object_by_hash($file->get_contenthash(), OBJECT_LOCATION_ERROR);
        }

        return $filehandle;
    }

    /**
     * Return a file handle for the specified path.
     *
     * This abstraction should be used when overriding get_content_file_handle in a new file system.
     *
     * @param string $path The path to the file. This shoudl be any type of path that fopen and gzopen accept.
     * @param int $type Type of file handle (FILE_HANDLE_xx constant)
     * @return resource
     * @throws coding_exception When an unexpected type of file handle is requested
     */
    protected function get_object_handle_for_path($path, $type = \stored_file::FILE_HANDLE_FOPEN) {
        switch ($type) {
            case \stored_file::FILE_HANDLE_FOPEN:
                $context = $this->externalclient->get_seekable_stream_context();
                return fopen($path, 'rb', false, $context);
            case \stored_file::FILE_HANDLE_GZOPEN:
                // Binary reading of file in gz format.
                return gzopen($path, 'rb');
            default:
                throw new \coding_exception('Unexpected file handle type');
        }
    }

    /**
     * Returns the client maximum allowed file size that is to be uploaded.
     *
     * @return int
     */
    public function get_maximum_upload_filesize() {
        return $this->externalclient->get_maximum_upload_size();
    }

    /**
     * Extends remove_file to delete file from remote object storage.
     *
     * @param string $contenthash
     */
    public function remove_file($contenthash) {
        if (!self::is_file_removable($contenthash)) {
            // Don't remove the file - it's still in use.
            return;
        }
        $this->delete_object_from_hash($contenthash);
    }

    /**
     * Extends recover_file to recover missing content of file.
     *
     * @param stored_file $file stored_file instance
     * @return bool success
     */
    protected function recover_file(\stored_file $file) {
        $contentfile = $this->get_external_path_from_storedfile($file);

        if (file_exists($contentfile) ) {
            // The file already exists on the external storage. No need to recover.
            return true;
        }

        return parent::recover_file($file);
    }

    /**
     * Calculate and return the contenthash of the supplied file.
     *
     * @param   string $filepath The path to the file on disk
     * @return  string The file's content hash
     */
    public static function hash_from_path($filepath) {
        return sha1_file($filepath);
    }

    /**
     * Copies file by its hash to directory specified at $destinationpath
     *
     * @param string $contenthash file to be copied
     * @param string $destinationpath destination directory
     * @return bool success
     */
    public function copy_file_from_hash_to_path($contenthash, $destinationpath) {
        $path = $this->get_remote_path_from_hash($contenthash);
        return copy($path, $destinationpath);
    }

    /**
     * Deletes external file depending on deleteexternal settings.
     *
     * @param string $contenthash file to be moved
     */
    public function delete_external_file_from_hash($contenthash, $force = false) {
        if ($force || (!empty($this->deleteexternally) && $this->deleteexternally == TOOL_OBJECTFS_DELETE_EXTERNAL_FULL)) {
            $currentpath = $this->get_external_path_from_hash($contenthash);
            $this->externalclient->delete_file($currentpath);
        }
    }

    /**
     * Deletes file from local/remote filesystem by its hash
     *
     * @param string $contenthash file to be copied
     */
    public function delete_object_from_hash($contenthash) {
        $location = $this->get_object_location_from_hash($contenthash);

        switch ($location) {
            case OBJECT_LOCATION_LOCAL:
                $this->delete_local_file_from_hash($contenthash);
                break;

            case OBJECT_LOCATION_DUPLICATED:
                $this->delete_local_file_from_hash($contenthash);
                $this->delete_external_file_from_hash($contenthash);
                break;

            case OBJECT_LOCATION_EXTERNAL:
                $this->delete_external_file_from_hash($contenthash);
                break;

            case OBJECT_LOCATION_ERROR:
            default:
                return;
        }
    }

    /**
     * Deletes file from local filesystem by its hash
     *
     * @param string $contenthash file to be deleted
     */
    public function delete_local_file_from_hash($contenthash) {
        $path = $this->get_local_path_from_hash($contenthash);
        unlink($path);
    }

    /**
     * Moves external file
     * if deleteexternally is enabled.
     *
     * @param string $currentpath current path to file to be moved.
     * @param string $destinationpath destination path.
     */
    public function rename_external_file($currentpath, $destinationpath) {
        if ($this->deleteexternally) {
            $this->externalclient->rename_file($currentpath, $destinationpath);
        }
    }

    /**
     * Return availability of external client.
     * @return mixed
     */
    public function get_client_availability() {
        return $this->externalclient->get_availability();
    }

    /**
     * Delete file with external client.
     *
     * @path   path to file to be deleted.
     * @return bool.
     */
    public function delete_client_file($path) {
        return $this->externalclient->delete_file($path);
    }

    /**
     * Copy from local to external file system by hash.
     *
     * @param string $contenthash File content hash.
     *
     * @return bool
     */
    public function copy_from_local_to_external($contenthash) {
        $localpath = $this->get_local_path_from_hash($contenthash);
        $externalpath = $this->get_external_path_from_hash($contenthash);

        return copy($localpath, $externalpath);
    }

    /**
     * Copy form external to local file system by hash.
     *
     * @param string $contenthash File content hash.
     *
     * @return bool
     */
    protected function copy_from_external_to_local($contenthash) {
        $localpath = $this->get_local_path_from_hash($contenthash);
        $externalpath = $this->get_external_path_from_hash($contenthash);

        return copy($externalpath, $localpath);
    }

    /**
     * Redirect to pre-signed URL to download file directly from external storage.
     *
     * @param  string  $contenthash  The content hash of the file to be served
     * @param  array   $headers      Request headers
     * @return bool
     * @throws \dml_exception
     */
    public function redirect_to_presigned_url($contenthash, $headers = array()) {
        global $FULLME;
        try {
            $signedurl = $this->externalclient->generate_presigned_url($contenthash, $headers);
            if (headers_sent()) {
                debugging('objectfs redirect for ' . $contenthash . ' from ' . $FULLME .
                        ': headers already sent; redirect may be incorrectly cached in browser');
            } else {
                // Remove all previously-set headers, and look for cache-control setting.
                $cachecontrol = '';
                foreach (headers_list() as $header) {
                    // Get header name (text before the colon).
                    if (preg_match('~^([^:]+):(.*)$~', $header, $matches)) {
                        [, $headername, $headervalue] = $matches;
                        if (strtolower($headername) === 'cache-control') {
                            $cachecontrol = $headervalue;
                        }
                        header_remove($headername);
                    }
                }
                // Set expires and cache-control values to match the presigned URL expiry, which may be
                // different to values previously set.
                header('Expires: '. gmdate('D, d M Y H:i:s', $signedurl->expiresat) .' GMT');
                // Unless cache-control was previously set to 'public' by Moodle for the actual file send,
                // use 'private' to allow browser caching only; otherwise via a shared cache users might
                // be able to redirect to content that was only supposed to be displayed to a different
                // user.
                $cachevisibility = 'private';
                if (strpos($cachecontrol, 'public') !== false) {
                    $cachevisibility = 'public';
                }
                header('Cache-Control: ' . $cachevisibility . ', max-age=' . ($signedurl->expiresat - time()));
            }
            redirect($signedurl->url);
        } catch (\Exception $e) {
            debugging('Failed to redirect to pre-signed url: ' . $e->getMessage());
            return false;
        }
    }


    /**
     * Return if the file system supports presigned_urls.
     * @return bool
     */
    public function supports_presigned_urls() {
        return false;
    }

    public function presigned_url_configured() {
        return $this->externalclient->support_presigned_urls()
            && $this->externalclient->enablepresignedurls;
    }

    /**
     * Returns true if the file system should redirect to pre-signed url.
     * This method takes file object as an argument to avoid extra lookups
     * and get file name and file size directly from the object.
     *
     * @param  object $file        File object
     * @return bool
     * @throws \dml_exception
     */
    public function presigned_url_should_redirect_file($file) {
        // Redirect when the file size is bigger than presignedminfilesize setting
        // and file extension is whitelisted.
        return ($file->get_filesize() >= $this->externalclient->presignedminfilesize &&
            manager::is_extension_whitelisted($file->get_filename()));
    }

    /**
     * Returns true if the file system should redirect to pre-signed url.
     *
     * @param  string $contenthash File content hash.
     * @param  array  $headers     Request headers.
     * @return bool
     * @throws \dml_exception
     */
    public function presigned_url_should_redirect($contenthash, $headers = array()) {
        // Redirect regardless.
        if ($this->externalclient->presignedminfilesize == 0 &&
                manager::all_extensions_whitelisted()) {
            return true;
        }

        // Do not redirect if the file extension is not whitelisted.
        // Try to retrieve the file name from headers.
        $disposition = manager::get_header($headers, 'Content-Disposition');
        $filename = manager::get_filename_from_header($disposition);
        if (!manager::is_extension_whitelisted($filename)) {
            return false;
        }

        // Try to retrieve the file name from the request path info.
        if (isset($_SERVER['PATH_INFO']) && !empty($_SERVER['PATH_INFO'])) {
            $path = urldecode($_SERVER['PATH_INFO']);
            $filename = basename($path);
            if (!manager::is_extension_whitelisted($filename)) {
                return false;
            }
        }

        // Redirect when the file size is bigger than presignedminfilesize setting.
        $filesize = $this->get_filesize_by_contenthash($contenthash);
        return ($filesize >= $this->externalclient->presignedminfilesize);
    }

    /**
     * Returns true to override xsendfile.
     *
     * @return bool
     */
    public function supports_xsendfile() {
        if (!$this->is_configured()) {
            return parent::supports_xsendfile();
        }
        return true;
    }

    /**
     * Add the supplied file to the file system and update its location.
     *
     * @param string $pathname Path to file currently on disk
     * @param string $contenthash SHA1 hash of content if known (performance only)
     * @return array (contenthash, filesize, newfile)
     */
    public function add_file_from_path($pathname, $contenthash = null) {
        $result = parent::add_file_from_path($pathname, $contenthash);

        $location = $this->get_object_location_from_hash($result[0]);
        manager::update_object_by_hash($result[0], $location);

        return $result;
    }

    /**
     * Add a file with the supplied content to the file system and update its location.
     *
     * @param string $content file content - binary string
     * @return array (contenthash, filesize, newfile)
     */
    public function add_file_from_string($content) {
        $result = parent::add_file_from_string($content);

        $location = $this->get_object_location_from_hash($result[0]);
        manager::update_object_by_hash($result[0], $location);

        return $result;
    }

    /**
     * Returns the total size of the filedir directory in bytes.
     * @return float|int
     */
    public function get_filedir_size() {
        global $CFG;
        if (empty($CFG->pathtodu)) {
            return 0;
        }
        $output = $this->exec_command("{$CFG->pathtodu} -sk " . escapeshellarg($this->filedir) . ' | cut -f1');
        // Convert kilobytes to bytes.
        return $output * 1000;
    }

    /**
     * Returns the number of files in the filedir directory.
     * @return int
     */
    public function get_filedir_count() {
        return $this->exec_command('/usr/bin/find  ' . escapeshellarg($this->filedir) . ' -type f | grep -c /');
    }

    /**
     * @param string $command
     * @return int
     */
    private function exec_command($command) {
        $io = popen($command, 'r');
        $output = fgets($io, 4096);
        pclose($io);
        return (int)$output;
    }

    /**
     * Copy content of file to given pathname.
     *
     * @param stored_file $file The file to be copied
     * @param string $target real path to the new file
     * @return bool success
     */
    public function copy_content_from_storedfile(stored_file $file, $target) {
        return $this->copy_file_from_hash_to_path($file->get_contenthash(), $target);
    }

    /**
     * Returns the size of the file by its contenthash.
     *
     * @param  string $contenthash Contenthash of the file
     * @return mixed String or false if the file not found
     * @throws \dml_exception
     */
    public function get_filesize_by_contenthash($contenthash) {
        global $DB;
        return $DB->get_field('files', 'filesize', ['contenthash' => $contenthash], IGNORE_MULTIPLE);
    }

    /**
     * Gets valid HTTP ranges for range request.
     * Throttles request length down to 5MB size if it's greater.
     *
     * @param  int          $filesize Size of the file to be served.
     * @return object|false           Array of range
     */
    public function get_valid_http_ranges($filesize) {
        $range = manager::get_header($_SERVER, 'HTTP_RANGE');
        if (!empty($range)) {
            preg_match('{bytes=(\d+)?-(\d+)?(,)?}i', $range, $matches);
            if (empty($matches[3])) {
                $ranges = new \stdClass();
                $ranges->rangefrom = (isset($matches[1])) ? intval($matches[1]) : 0;
                $ranges->rangeto = (isset($matches[2])) ? intval($matches[2]) : ($filesize - 1);
                $ranges->length = $ranges->rangeto - $ranges->rangefrom + 1;
                if ($ranges->length > 5242880) {
                    // Stream files in 5MB-chunks.
                    $ranges->rangeto = $ranges->rangefrom + 5242880 - 1;
                    $ranges->length = 5242880;
                }
                return $ranges;
            }
        }
        return false;
    }

    /**
     * Returns true if object file system is configured.
     *
     * @return bool
     */
    public function is_configured() {
        global $CFG;

        // Return false if alternative_file_system_class is not set in config.php.
        if (empty($CFG->alternative_file_system_class)) {
            return false;
        }

        // Return false if there is a disparity between filesystem set in config.php and admin settings.
        if ($CFG->alternative_file_system_class != '\\' . get_class($this)) {
            return false;
        }

        // Return false if the client SDK does not exist or has not been loaded.
        if (!$this->get_client_availability()) {
            return false;
        }

        // Looks like all checks have been passed.
        return true;
    }

    /**
     * No cleanup required - don't trigger filesystem trash clear.
     */
    public function cron() {
        return true;
    }

    /**
     * Object fs doesn't use trashdir.
     *
     * @param string $contenthash The content hash
     * @return string The full path to the trash directory
     */
    protected function get_trash_fulldir_from_hash($contenthash) {
        return '';
    }

    /**
     * Object fs doens't use trashdir - trigger exception.
     *
     * @param string $contenthash The content hash
     * @return string The full path to the trash file
     */
    protected function get_trash_fullpath_from_hash($contenthash) {
        return '';
    }
}
