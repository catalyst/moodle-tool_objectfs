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
use core_files\filestorage\file_exception;
use Aws\S3\Exception\S3Exception;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/admin/tool/sssfs/lib.php');

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
    public function __construct($client, $filesystem, $config) {
        parent::__construct($client, $filesystem, $config->maxtaskruntime);
        $this->sizethreshold = $config->sizethreshold;
        $this->minimumage = $config->minimumage;

    }

    /**
     * Get candidate content hashes for pushing.
     * Files that are bigger than the sizethreshold,
     * older than the minimum age
     * and have no state / are in local state.
     *
     * @return array candidate contenthashes
     */
    public function get_candidate_content_hashes() {
        global $DB;
        $sql = 'SELECT F.contenthash
                FROM {files} F
                LEFT JOIN {tool_sssfs_filestate} SF on F.contenthash = SF.contenthash
                GROUP BY F.contenthash, F.filesize, SF.state
                HAVING MIN(F.timecreated) < ? AND MAX(F.filesize) > ?
                AND (SF.state IS NULL OR SF.state = ?)';

        $maxcreatedtimestamp = time() - $this->minimumage;

        $params = array($maxcreatedtimestamp, $this->sizethreshold, SSS_FILE_STATE_LOCAL);

        $contenthashes = $DB->get_fieldset_sql($sql, $params);

        return $contenthashes;
    }

    /**
     * Pushes files from local file system to S3.
     *
     * @param  array $candidatehashes content hashes to push
     */
    public function execute($candidatehashes) {
        global $DB;

        foreach ($candidatehashes as $contenthash) {
            if (time() >= $this->finishtime) {
                break;
            }

            try {
                $filecontent = $this->filesystem->get_local_content_from_contenthash($contenthash);
                $result = $this->client->push_file($contenthash, $filecontent);
                if ($result) {
                    log_file_state($contenthash, SSS_FILE_STATE_DUPLICATED);
                }
            } catch (file_exception $e) {
                mtrace($e);
                continue;
            } catch (S3Exception $e) {
                mtrace($e);
                continue;
            }
        }
    }
}


