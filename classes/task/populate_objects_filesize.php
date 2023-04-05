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

namespace tool_objectfs\task;

use core\task\adhoc_task;
use core\task\manager;

/**
 * Ad-hoc task to update objects table. Used for async upgrade.
 *
 * @package    tool_objectfs
 * @author     Andrew Madden <andrewmadden@catalyst-au.net>
 * @copyright  2022 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class populate_objects_filesize extends adhoc_task {

    /** @var int Max number of updates that task should trigger before scheduling a new task. */
    private const MAX_UPDATES = 100000;

    /**
     * Action of task.
     */
    public function execute() {
        global $DB;

        // Get the custom data.
        $data = $this->get_custom_data();
        $maxupdates = !empty($data->maxupdates) ? $data->maxupdates : self::MAX_UPDATES;

        // Get all objects without a filesize and join them to a filesize from the files table.
        // Values less than 0 for object's location indicate an error for the object.
        $sql = "SELECT o.id, o.contenthash, o.timeduplicated, o.location, f.filesize
                  FROM {tool_objectfs_objects} o
                  JOIN {files} f ON o.contenthash = f.contenthash
                 WHERE o.filesize IS NULL
                   AND o.location >= 0
              GROUP BY o.id,
                       o.contenthash,
                       f.filesize";
        $records = $DB->get_recordset_sql($sql, null, 0, $maxupdates + 1);

        // If more records found than the max number of updates, only process max updates then queue new task.
        $queueadditionaltask = false;

        $updatecount = 0;
        foreach ($records as $record) {
            if ($updatecount >= $maxupdates) {
                $queueadditionaltask = true;
                break;
            }
            $DB->update_record('tool_objectfs_objects', $record, true);
            $updatecount += 1;
        }
        $records->close();
        if (!PHPUNIT_TEST) {
            mtrace("$updatecount records updated");
        }

        if ($queueadditionaltask) {
            if (!PHPUNIT_TEST) {
                mtrace("Queueing additional task");
            }
            manager::queue_adhoc_task(new populate_objects_filesize());
        }
    }
}
