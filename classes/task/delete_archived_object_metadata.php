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
 * Task that checks for old archived objects, and removes their metadata
 * (record) as it is no longer useful/relevant.
 *
 * @package   tool_objectfs
 * @author    Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\task;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../lib.php');

class delete_archived_object_metadata extends task {

    /** @var string $stringname */
    protected $stringname = 'delete_archived_object_metadata_task';

    /**
     * Execute task
     */
    public function execute() {
        global $DB;

        $wheresql = 'location = :location and timeduplicated < :ageforremoval';
        $ageforremoval = $this->config->maxarchivedage;
        if (empty($ageforremoval)) {
            mtrace('Skipping deletion of archived object metadata as maxarchivedage is set to an empty value.');
            return;
        }

        $params = [
            'location' => OBJECT_LOCATION_ARCHIVED,
            'ageforremoval' => time() - $ageforremoval
        ];
        $count = $DB->count_records_select('tool_objectfs_objects', $wheresql, $params);
        if (!empty($count)) {
            mtrace("Deleting $count records with orphaned metadata (archived tool_objectfs_objects)");
            $DB->delete_records_select('tool_objectfs_objects', $wheresql, $params);
        }
    }
}
