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

namespace tool_objectfs\local\report;

/**
 * Tag count report builder.
 *
 * @package   tool_objectfs
 * @author    Matthew Hilton <matthewhilton@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tag_count_report_builder extends objectfs_report_builder {
    /**
     * Builds report
     * @param int $reportid
     * @return objectfs_report
     */
    public function build_report($reportid) {
        global $DB;
        $report = new objectfs_report('tag_count', $reportid);

        // Returns counts of key:value.
        $sql = "
            SELECT CONCAT(COALESCE(object_tags.tagkey, '(untagged)'), ': ', COALESCE(object_tags.tagvalue, '')) as datakey,
                   COUNT(DISTINCT object_tags.objectid) as objectcount
              FROM {tool_objectfs_object_tags} object_tags
          GROUP BY object_tags.tagkey, object_tags.tagvalue
        ";
        $result = $DB->get_records_sql($sql);
        $report->add_rows($result);
        return $report;
    }
}
