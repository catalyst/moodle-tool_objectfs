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
 * Task that deletes empty dirs from $CFG->filedir.
 *
 * @package   tool_objectfs
 * @author    Gleimer Mora <gleimermora@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\task;

use coding_exception;
use Generator;
use tool_objectfs\local\object_manipulator\checker_filedir;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/admin/tool/objectfs/lib.php');

class check_filedir_location  extends \core\task\scheduled_task {

    /**
     * Get task name
     * @return string
     * @throws coding_exception
     */
    public function get_name() {
        return get_string('check_filedir_location_task', 'tool_objectfs');
    }

    /**
     * Execute task
     * @throws coding_exception
     */
    public function execute() {
        $config = get_objectfs_config();
        if (!tool_objectfs_should_tasks_run()) {
            mtrace(get_string('not_enabled', 'tool_objectfs'));
            return;
        }
        $filesystem = new $config->filesystem();
        if (!$filesystem->get_client_availability()) {
            mtrace(get_string('client_not_available', 'tool_objectfs'));
            return;
        }
        $logger = new \tool_objectfs\log\aggregate_logger();
        $manipulator = new checker_filedir($filesystem, $config, $logger);
        /** @var Generator $gen */
        $gen = $filesystem->scan_dir();
        foreach ($gen as $obj) {
            if ($manipulator->has_exceeded_run_time()) {
                set_config('lastprocessed', $obj->contenthash, 'tool_objectfs');
                mtrace('. Max execution time reached');
                break;
            }
            $manipulator->execute([$obj]);
        }

        if (!$gen->valid()) {
            // We have processed all of the files.
            set_config('lastprocessed', '', 'tool_objectfs');
            mtrace('. All files completely processed');
        }
    }
}
