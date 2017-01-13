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

class puller extends manipulator {

    /**
     * Size threshold for pulling files from S3 in bytes.
     *
     * @var int
     */
    private $sizethreshold;

    /**
     * Puller constructor.
     *
     * @param sss_client $client S3 client
     * @param sss_file_system $filesystem S3 file system
     * @param object $config sssfs config.
     */
    public function __construct($config, $client) {
        parent::__construct($client, $config->maxtaskruntime);
        $this->sizethreshold = $config->sizethreshold;
    }

    /**
     * Get candidate content hashes for pulling.
     * Files that are less or equal to the sizethreshold,
     * and are external.
     *
     * @return array candidate contenthashes
     */
    public function get_candidate_content_hashes() {
        global $DB;
        $sql = 'SELECT F.contenthash
                FROM {files} F
                LEFT JOIN {tool_sssfs_filestate} SF on F.contenthash = SF.contenthash
                GROUP BY F.contenthash, F.filesize, SF.location
                HAVING MAX(F.filesize) <= ?
                AND (SF.location = ?)';

        $params = array($this->sizethreshold, SSS_FILE_LOCATION_EXTERNAL);

        $contenthashes = $DB->get_fieldset_sql($sql, $params);

        return $contenthashes;
    }

    /**
     * Copy file from s3 to local storage.
     *
     * @param  string $contenthash files contenthash
     *
     * @return bool success of operation
     */
    private function copy_sss_file_to_local($contenthash) {
        $localfilepath = $this->get_local_fullpath_from_hash($contenthash);
        $sssfilepath = $this->client->get_sss_fullpath_from_hash($contenthash);
        return copy($sssfilepath, $localfilepath);
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
                $success = $this->copy_sss_file_to_local($contenthash);
                if ($success) {
                    log_file_state($contenthash, SSS_FILE_LOCATION_DUPLICATED);
                }
            } catch (file_exception $e) {
                mtrace($e);
                continue;
            } catch (S3Exception $e) {
                mtrace($e->getMessage());
                continue;
            }
        }
    }
}


