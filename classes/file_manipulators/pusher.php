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
 * Pushes files to s3 if they meet the configured criterea.
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

class pusher extends manipulator {

    /**
     * Size threshold for pushing files to S3 in bytes.
     *
     * @var int
     */
    private $sizethreshold;

    /**
     * Minimum age of a file to be pushed to S3 in seconds.
     *
     * @var int
     */
    private $minimumage;

    /**
     * Pusher constructor.
     *
     * @param sss_client $client S3 client
     * @param sss_file_system $filesystem S3 file system
     * @param object $config sssfs config.
     */
    public function __construct($config, $client) {
        parent::__construct($client, $config->maxtaskruntime);
        $this->sizethreshold = $config->sizethreshold;
        $this->minimumage = $config->minimumage;
    }

    /**
     * Get candidate content hashes for pushing.
     * Files that are bigger than the sizethreshold,
     * less than 5GB (S3 upload max),
     * older than the minimum age
     * and have no location / are in local.
     *
     * @return array candidate contenthashes
     */
    public function get_candidate_files() {
        global $DB;
        $sql = 'SELECT f.contenthash,
                       MAX(f.filesize) as filesize
                  FROM {files} f
             LEFT JOIN {tool_sssfs_filestate} sf ON f.contenthash = sf.contenthash
              GROUP BY f.contenthash,
                       f.filesize,
                       sf.location
                HAVING MIN(f.timecreated) < ?
                   AND MAX(f.filesize) > ?
                   AND MAX(f.filesize) < 5000000000
                   AND (sf.location IS NULL OR sf.location = ?)';

        $maxcreatedtimestamp = time() - $this->minimumage;

        $params = array($maxcreatedtimestamp, $this->sizethreshold, SSS_FILE_LOCATION_LOCAL);

        $starttime = time();
        $files = $DB->get_records_sql($sql, $params);
        $duration = time() - $starttime;
        $count = count($files);

        $logstring = "File pusher query took $duration seconds to find $count files \n";
        mtrace($logstring);
        return $files;
    }

    /**
     * Copy file from local to s3 storage.
     *
     * @param  string $contenthash files contenthash
     *
     * @return bool success of operation
     */
    private function copy_local_file_to_sss($contenthash) {
        $localfilepath = $filepath = $this->get_local_fullpath_from_hash($contenthash);
        $sssfilepath = $this->client->get_sss_fullpath_from_hash($contenthash);

        if (is_readable($sssfilepath)) {
            return true; // Already there.
        }

        $this->ensure_path_is_readable($localfilepath);
        return copy($localfilepath, $sssfilepath);
    }

    /**
     * Calculated md5 of file.
     *
     * @param  string $contenthash files contenthash
     *
     * @return string md5 hash of file
     */
    private function get_local_md5_from_contenthash($contenthash) {
        $localfilepath = $this->get_local_fullpath_from_hash($contenthash);
        $md5 = md5_file($localfilepath);
        return $md5;
    }

    /**
     * Pushes files from local file system to S3.
     *
     * @param  array $candidatehashes content hashes to push
     */
    public function execute($files) {
        global $DB;

        $starttime = time();
        $filecount = 0;
        $totalfilesize = 0;

        foreach ($files as $file) {
            if (time() >= $this->finishtime) {
                break;
            }

            try {
                $success = $this->copy_local_file_to_sss($file->contenthash);
                if ($success) {
                    $filemd5 = $this->get_local_md5_from_contenthash($file->contenthash);
                    log_file_state($file->contenthash, SSS_FILE_LOCATION_DUPLICATED, $filemd5);
                    $filecount++;
                    $totalfilesize += $file->filesize;
                }
            } catch (file_exception $e) {
                mtrace($e);
                continue;
            } catch (S3Exception $e) {
                mtrace($e->getMessage());
                continue;
            }
        }
        $duration = time() - $starttime;

        $totalfilesize = display_size($totalfilesize);
        $logstring = "File pusher pushed $filecount files, $totalfilesize to S3 in $duration seconds \n";
        mtrace($logstring);
    }
}


