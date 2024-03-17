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

use action_link;
use core\check\check;
use core\check\result;
use moodle_url;
use tool_objectfs\local\manager;

/**
 * Configuration check.
 *
 * @package    tool_objectfs
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class configuration extends check {
    /**
     * Gets the result of the check
     * @return result
     */
    public function get_result(): result {
        // Load objectfs and run a test.
        $config = manager::get_objectfs_config();
        $client = manager::get_client($config);

        // Something is very wrong if this is false.
        if (empty($client)) {
            return new result(result::UNKNOWN, get_string('check:configuration:empty', 'tool_objectfs'));
        }

        return $client->test_configuration();
    }

    /**
     * Return action link
     * @return action_link
     */
    public function get_action_link(): ?action_link {
        $str = get_string('check:settings', 'tool_objectfs');
        $url = new moodle_url('/admin/category.php', ['category' => 'tool_objectfs']);
        return new action_link($url, $str);
    }
}
