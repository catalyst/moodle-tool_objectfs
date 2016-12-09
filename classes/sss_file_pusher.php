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

namespace tool_sssfs;
require_once($CFG->dirroot . '/admin/tool/sssfs/lib.php');

defined('MOODLE_INTERNAL') || die();

class sss_file_pusher {
    private $client;
    private $filesystem;
    private $sizethreshold;
    private $minimumage;
    private $maxruntime;

    public function __construct($client, $filesystem, $config) {
        $this->client = $client;
        $this->filesystem = $filesystem;
        $this->sizethreshold = $config->sizethreshold;
        $this->minimumage = $config->minimumage;
        $this->maxruntime = 60; // Seconds.
    }

    public function push() {
        global $DB;

        $finishtime = time() + $this->maxruntime;

        // This Should filter down the file list the most so we do this first.
        // TODO: refactor into get_push_candidate_content_hashes.
        $contenthashestopush = $this->get_content_hashes_over_threshold($this->sizethreshold);

        $ssscontenthashes = $this->get_content_hashes_in_sss();

        foreach ($contenthashestopush as $contenthash) {
            if (time() > $finishtime) {
                break;
            }

            if (in_array($contenthash, $ssscontenthashes)) {
                continue;
            }

            $filecontent = $this->filesystem->get_content_from_hash($contenthash);

            if ($filecontent) {
                // TODO: deal with response.
                $response = $this->client->push_file($contenthash, $filecontent);
                log_file_state($contenthash, SSS_FILE_STATE_DUPLICATED);
            }
        }
    }

    private function get_content_hashes_over_threshold($threshold) {
        global $DB;
        $sql = "SELECT DISTINCT contenthash FROM {files} WHERE filesize > ?";
        $contenthashes = $DB->get_fieldset_sql($sql, array($threshold));
        return $contenthashes;
    }

    private function get_content_hashes_in_sss() {
        global $DB;
        $sql = 'SELECT contenthash FROM {tool_sssfs_filestate} WHERE STATE in (?, ?)';
        $ssscontenthashes = $DB->get_fieldset_sql($sql, array(SSS_FILE_STATE_DUPLICATED, SSS_FILE_STATE_EXTERNAL));
        return $ssscontenthashes;
    }
}


