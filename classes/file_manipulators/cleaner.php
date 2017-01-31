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
 * Deletes files that are old enough and are in S3.
 *
 * @package   tool_sssfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_sssfs\file_manipulators;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/admin/tool/sssfs/lib.php');

use core_files\filestorage\file_exception;
use Aws\S3\Exception\S3Exception;

class cleaner extends manipulator {

    /**
     * How long file must exist after
     * duplication before it can be deleted.
     *
     * @var int
     */
    private $consistencydelay;

    /**
     * Whether to delete local files
     * once they are in s3.
     *
     * @var bool
     */
    private $deletelocal;

    /**
     * Cleaner constructor.
     *
     * @param sss_client $client S3 client
     * @param sss_file_system $filesystem S3 file system
     * @param object $config sssfs config.
     */
    public function __construct($config, $client) {
        parent::__construct($client, $config->maxtaskruntime);
        $this->consistencydelay = $config->consistencydelay;
        $this->deletelocal = $config->deletelocal;
    }

    /**
     * Get candidate content hashes for cleaning.
     * Files that are past the consistancy delay
     * and are in location duplicated.
     *
     * @return array candidate contenthashes
     */
    public function get_candidate_files() {
        global $DB;

        if ($this->deletelocal == 0) {
            mtrace("Delete local disabled, not running query \n");
            return array();
        }

        $sql = 'SELECT f.contenthash,
                       MAX(f.filesize) AS filesize,
                       sf.md5
                  FROM {files} f
             LEFT JOIN {tool_sssfs_filestate} sf ON f.contenthash = sf.contenthash
                 WHERE sf.timeduplicated <= ?
                       AND sf.location = ?
              GROUP BY f.contenthash,
                       f.filesize,
                       sf.location,
                       sf.md5';

        $consistancythrehold = time() - $this->consistencydelay;
        $params = array($consistancythrehold, SSS_FILE_LOCATION_DUPLICATED);

        $starttime = time();
        $files = $DB->get_records_sql($sql, $params);
        $duration = time() - $starttime;
        $count = count($files);

        $logstring = "File cleaner query took $duration seconds to find $count files \n";
        mtrace($logstring);

        return $files;
    }

    /**
     * Deletes local file based on it's content hash.
     *
     * @param  string $contenthash files contenthash
     *
     * @return bool success of operation
     */
    private function delete_local_file_from_contenthash($contenthash) {
        $filepath = $this->get_local_fullpath_from_hash($contenthash);
        return unlink($filepath);
    }

    /**
     * Cleans local file system of candidate hash files.
     *
     * @param  array $candidatehashes content hashes to delete
     */
    public function execute($files) {
        global $DB;

        $starttime = time();
        $filecount = 0;
        $totalfilesize = 0;

        if ($this->deletelocal == 0) {
            mtrace("Delete local disabled, not deleting \n");
            return;
        }

        foreach ($files as $file) {

            if (time() >= $this->finishtime) {
                break;
            }

            try {
                $sssfilepath = $this->client->get_sss_filepath_from_hash($file->contenthash);
                $fileinsss = $this->client->check_file($sssfilepath, $file->md5);
                if ($fileinsss) {
                    $success = $this->delete_local_file_from_contenthash($file->contenthash);
                    if ($success) {
                        log_file_state($file->contenthash, SSS_FILE_LOCATION_EXTERNAL);
                        $filecount++;
                        $totalfilesize += $file->filesize;
                    }
                } else {
                    mtrace("File not in sss: $sssfilepath. Setting state back to local\n");
                    log_file_state($file->contenthash, SSS_FILE_LOCATION_LOCAL);
                }
            } catch (file_exception $e) {
                $this->log_error($e, $file->contenthash);
                continue;
            } catch (S3Exception $e) {
                $this->log_error($e, $file->contenthash);
                continue;
            }
        }
        $duration = time() - $starttime;

        $totalfilesize = display_size($totalfilesize);
        $logstring = "File cleaner cleaned $filecount files, $totalfilesize in $duration seconds \n";
        mtrace($logstring);
    }
}
