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

defined('MOODLE_INTERNAL') || die();

class sss_file_pusher {

    private $client;
    private $filesystem;
    private $sizethreshold;

    public function __construct($client, $filesystem) {
        $this->client = $client;
        $this->filesystem = $filesystem;
        $this->sizethreshold = 1000;
    }

    public function push() {
        global $DB;

        $contenthashestopush = get_content_hashes_over_threshold($this->sizethreshold);

        $ssscontenthashes = get_content_hashes_in_sss();

        foreach ($contenthashestopush as $contenthash) {

            if (in_array($contenthash, $ssscontenthashes)) {
                continue;
            }

            $filecontent = $this->filesystem->get_content_from_hash($contenthash);

            if ($filecontent) {
                // TODO: deal with response.
                $response = $this->client->push_file($contenthash, $filecontent);
                $logger->log_file_state($contenthash, SSS_FILE_STATE_DUPLICATED);
            }
        }
    }
}


