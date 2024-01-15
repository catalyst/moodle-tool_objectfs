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
use coding_exception;
use core\check\check;
use core\check\result;
use moodle_url;
use Throwable;
use tool_objectfs\local\manager;

/**
 * Store check.
 *
 * @package    tool_objectfs
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class store extends check {
    /** @var string The selected type of store check **/
    private $type;

    /** @var string Connection test type **/
    public const TYPE_CONNECTION = 'connection';

    /** @var string Permissions test type **/
    public const TYPE_PERMISSIONS = 'permissions';

    /** @var string Range request test type **/
    public const TYPE_RANGEREQUEST = 'rangerequest';

    /** @var array Available test types **/
    public const TYPES = [self::TYPE_CONNECTION, self::TYPE_PERMISSIONS, self::TYPE_RANGEREQUEST];

    /**
     * Create a store check for a given type
     * @param string $type one of TYPES
     */
    public function __construct(string $type) {
        if (!in_array($type, self::TYPES)) {
            throw new coding_exception("Given test type " . $type . " is not valid.");
        }

        $this->type = $type;
    }

    /**
     * Returns the id - differs based on the type
     * @return string
     */
    public function get_id(): string {
        return "store_check_" . $this->type;
    }

    /**
     * Return the result
     * @return result
     */
    public function get_result(): result {
        try {
            // Check if configured first, and report NA if not configured.
            if (!\tool_objectfs\local\manager::check_file_storage_filesystem()) {
                return new result(result::NA, get_string('check:notenabled', 'tool_objectfs'));
            }

            // Load objectfs and run a test.
            $config = manager::get_objectfs_config();
            $client = manager::get_client($config);

            // Something is very wrong if this is empty.
            if (empty($client)) {
                return new result(result::UNKNOWN, get_string('check:configuration:empty', 'tool_objectfs'));
            }

            // If not configured yet, don't bother testing connection or permissions.
            if ($client->test_configuration()->get_status() != result::OK) {
                return new result(result::NA, get_string('check:storecheck:notconfiguredskip', 'tool_objectfs'));
            }

            switch($this->type) {
                case self::TYPE_CONNECTION:
                    return $client->test_connection(false);

                case self::TYPE_RANGEREQUEST:
                    return $client->test_range_request(new $config->filesystem());

                case self::TYPE_PERMISSIONS:
                    return $client->test_permissions(false);
            }
        } catch (Throwable $e) {
            // Usually the SDKs will throw exceptions if something doesn't work, so we want to catch these.
            return new result(result::CRITICAL, get_string('check:storecheck:error', 'tool_objectfs')
                . $this->type . ': ' . $e->getMessage(), $e->getTraceAsString());
        }
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
