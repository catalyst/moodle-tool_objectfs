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

/**
 * Status check for objectFS proxied range requests.
 *
 * @package    tool_objectfs
 * @author     Peter Burnett <peterburnett@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class proxy_range_request extends check {
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
     * Check for the success of a proxied range request, if the setting is enabled.
     *
     * @return result
     */
    public function get_result(): result {
        $config = \tool_objectfs\local\manager::get_objectfs_config();
        $client = \tool_objectfs\local\manager::get_client($config);

        $signingsupport = false;
        if (!empty($config->filesystem)) {
            $signingsupport = (new $config->filesystem())->supports_presigned_urls();
        }

        $testconn = $client->test_connection();
        $connstatus = $testconn->success;

        if ($connstatus && $signingsupport) {
            $range = $client->test_range_request(new $config->filesystem());

            if ($range->result) {
                return new result(result::OK, get_string('settings:presignedurl:testrangeok', 'tool_objectfs'));
            } else {
                return new result(result::WARNING, get_string('settings:presignedurl:testrangeerror', 'tool_objectfs'));
            }
        }

        return new result(result::UNKNOWN, get_string('check:proxyrangerequestsdisabled', 'tool_objectfs'));
    }
}
