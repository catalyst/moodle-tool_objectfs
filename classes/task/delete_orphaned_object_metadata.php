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
 * Task that checks for old orphaned objects, and removes their metadata (record)
 * and external file (if delete external enabled) as it is no longer useful/relevant.
 *
 * @package   tool_objectfs
 * @author    Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\task;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../lib.php');

class delete_orphaned_object_metadata extends task {

    /** @var string $stringname */
    protected $stringname = 'delete_orphaned_object_metadata_task';

    /**
     * Execute task
     */
    public function execute() {
        global $DB;

        $ageforremoval = $this->config->maxorphanedage;
        if (empty($ageforremoval)) {
            mtrace('Skipping deletion of orphaned object metadata as maxorphanedage is set to an empty value.');
            return;
        }

        $params = [
            'location' => OBJECT_LOCATION_ORPHANED,
            'ageforremoval' => time() - $ageforremoval
        ];

        // Check for delay deletion if enabled.
        $delayquery = '';
        if (!empty($this->config->delaydeleteexternalobject)) {
            $params['deletetime'] = time() - $this->config->delaydeleteexternalobject;
            $delayquery = 'AND o.timeorphaned < :deletetime';
        }

        if (!empty($this->config->deleteexternal) && $this->config->deleteexternal == TOOL_OBJECTFS_DELETE_EXTERNAL_TRASH) {
            // We need to delete the external files as well as the orphaned data.
            $filesystem = new $this->config->filesystem();

            // Join with files table to make extra sure we aren't deleting something that already exists.
            $sql = "SELECT o.*
                      FROM {tool_objectfs_objects} o
                 LEFT JOIN {files} f ON o.contenthash = f.contenthash
                     WHERE f.id is null AND o.location = :location AND o.timeduplicated < :ageforremoval
                           $delayquery";

            $objects = $DB->get_recordset_sql($sql, $params);
            $count = 0;
            foreach ($objects as $object) {
                // Delete the external file.
                $filesystem->delete_external_file_from_hash($object->contenthash, true);
                // Delete the metadata in the object table.
                $DB->delete_records('tool_objectfs_objects', ['id' => $object->id]);
                $count++;
            }
            $objects->close();
            mtrace("Deleted $count orphaned files and their metadata (orphaned tool_objectfs_objects)");
        } else {
            // Delete external files is turned off, we only delete the metadata.
            $wheresql = 'location = :location and timeduplicated < :ageforremoval';
            $count = $DB->count_records_select('tool_objectfs_objects', $wheresql, $params);
            if (!empty($count)) {
                mtrace("Deleting $count records with orphaned metadata (orphaned tool_objectfs_objects)");
                $DB->delete_records_select('tool_objectfs_objects', $wheresql, $params);
            }
        }
    }
}
