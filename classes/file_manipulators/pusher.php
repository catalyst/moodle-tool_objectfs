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

class pusher extends manipulator {
    private $sizethreshold;
    private $minimumage;

    public function __construct($client, $filesystem, $config) {
        parent::__construct($client, $filesystem, $config->maxtaskruntime);
        $this->sizethreshold = $config->sizethreshold;
        $this->minimumage = $config->minimumage;

    }

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

    public function execute($candidatehashes) {
        global $DB;

        foreach ($candidatehashes as $contenthash) {
            if (time() > $this->finishtime) {
                break;
            }

            $filecontent = $this->filesystem->get_content_from_hash($contenthash);

            if ($filecontent !== false) {
                $success = $this->client->push_file($contenthash, $filecontent);
                if ($success) {
                    log_file_state($contenthash, SSS_FILE_STATE_DUPLICATED);
                }
            }
        }
    }
}


