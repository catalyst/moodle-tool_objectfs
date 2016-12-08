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
 * @package   tool_sssfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_sssfs\task;
use tool_sssfs\sss_client;
use tool_sssfs;

require(dirname(dirname(dirname(__FILE__))).'/lib.php');

defined('MOODLE_INTERNAL') || die();

/**
 *  Task that pushes files to S3.
 *
 * @package   tool_sssfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class push_to_sss extends \core\task\scheduled_task {

    /**
     * Get task name
     */
    public function get_name() {
        return get_string('push_to_sss_task', 'tool_sssfs');
    }

    /**
     * Execute task
     */
    public function execute() {
        echo 's3 upload task';

        $s3client = new sss_client();
        $filesystem = \tool_sssfs\sss_file_system::instance();

        $contenthashestopush = $filesystem->get_content_hashes_over_threshold(1000);

        // TODO: Filter based on objects already there.

        foreach ($contenthashestopush as $contenthash) {

            $filecontent = $filesystem->get_content_from_hash($contenthash);

            if ($filecontent) {
                $s3client->push_file($contenthash, $filecontent);
            }
        }
    }
}


