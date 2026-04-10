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

namespace tool_objectfs\check;

use core\check\check;
use core\check\result;
use tool_objectfs\local\manager;

/**
 * Status check for objectFS connection to cloud storage.
 *
 * @package    tool_objectfs
 * @author     Abhinav Gandham <abhinavgandham@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class connection extends check {
    /**
     * Link to ObjectFS settings page.
     *
     * @return \action_link|null
     */
    public function get_action_link(): ?\action_link {
        $url = new \moodle_url('/admin/category.php', ['category' => 'tool_objectfs']);
        return new \action_link($url, get_string('pluginname', 'tool_objectfs'));
    }

    /**
     * Get the result of the connection check if the config is not empty.
     *
     * @return result
     */
    public function get_result(): result {
        $config = manager::get_objectfs_config();
        $client = manager::get_client($config);

        if (empty($client) || !$client->is_configured($config)) {
            return new result(result::NA, get_string('check:connection:na', 'tool_objectfs'));
        }

        $connection = $client->test_connection();

        if ($connection->success) {
            return new result(result::OK, get_string('check:connection:ok', 'tool_objectfs'));
        }

        return new result(result::ERROR, get_string('check:connection:error', 'tool_objectfs', $connection->details));
    }
}
