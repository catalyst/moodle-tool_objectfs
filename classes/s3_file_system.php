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
 * At minimum you need to implement get_remote_client.
 *
 * @package   tool_objectfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs;

defined('MOODLE_INTERNAL') || die();

use tool_objectfs\client\s3_client;

require_once($CFG->dirroot . '/admin/tool/objectfs/lib.php');

class s3_file_system extends object_file_system {

    protected function get_external_client($config) {
        $s3client = new s3_client($config);

        return $s3client;
    }

    public function copy_from_local_to_external($contenthash) {
        $config = get_objectfs_config();
        $s3client = $this->get_external_client($config);
        $localpath = $this->get_local_path_from_hash($contenthash);

        try {
            $s3client->upload_to_s3($localpath, $contenthash);
            return true;
        } catch (\Exception $e) {
            $this->get_logger()->error_log(
                'ERROR: copy ' . $localpath . ' to ' . $this->get_external_path_from_hash($contenthash) . ': ' . $e->getMessage()
            );
            return false;
        }
    }
}
