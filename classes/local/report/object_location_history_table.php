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
 * Object location history table.
 *
 * @package   tool_objectfs
 * @author    Mikhail Golenkov <golenkovm@gmail.com>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\local\report;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/tablelib.php');

/**
 * Table to display file location history.
 *
 * @author     Mikhail Golenkov <golenkovm@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class object_location_history_table extends \table_sql {

    /**
     * Constructor for the file location history table.
     */
    public function __construct() {
        parent::__construct('locationhistory');

        // TODO: Replace by lang strings.
        $columnheaders = [
            'date' => get_string('date'),
            'local_count' => get_string('object_status:location:localcount', 'tool_objectfs'),
            'local_size' => get_string('object_status:location:localsize', 'tool_objectfs'),
            'duplicated_count' => get_string('object_status:location:duplicatedcount', 'tool_objectfs'),
            'duplicated_size' => get_string('object_status:location:duplicatedsize', 'tool_objectfs'),
            'external_count' => get_string('object_status:location:externalcount', 'tool_objectfs'),
            'external_size' => get_string('object_status:location:externalsize', 'tool_objectfs'),
            'missing_count' => get_string('object_status:location:missingcount', 'tool_objectfs'),
            'missing_size' => get_string('object_status:location:missingsize', 'tool_objectfs'),
            'total_count' => get_string('object_status:location:totalcount', 'tool_objectfs'),
            'total_size' => get_string('object_status:location:totalsize', 'tool_objectfs'),
            'filedir_count' => get_string('object_status:location:filedircount', 'tool_objectfs'),
            'filedir_size' => get_string('object_status:location:filedirsize', 'tool_objectfs'),
            'delta_count' => get_string('object_status:location:deltacount', 'tool_objectfs'),
            'delta_size' => get_string('object_status:location:deltasize', 'tool_objectfs'),
        ];

        $this->is_downloadable(true);
        $this->define_columns(array_keys($columnheaders));
        $this->define_headers(array_values($columnheaders));
        $this->define_header_column('date');
        $this->collapsible(false);
        $this->sortable(false);
        $this->pageable(false);
    }

    /**
     * Query the db. Store results in the table object for use by build_table.
     *
     * @param int $pagesize size of page for paginated displayed table.
     * @param bool $useinitialsbar do you want to use the initials bar. Bar
     * will only be used if there is a fullname column defined for the table.
     * @throws \dml_exception
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        global $DB;
        $sql = 'SELECT CONCAT(timecreated, datakey) AS uid,
                       timecreated AS date,
                       datakey AS location,
                       objectcount AS count,
                       objectsum AS size
                  FROM {tool_objectfs_reports}
                 WHERE reporttype = :reporttype
              ORDER BY timecreated';
        $params = array('reporttype' => 'location');
        $rawrecords = $DB->get_records_sql($sql, $params, 0, 5000);
        $reportdates = objectfs_report::get_report_dates();

        foreach ($reportdates as $timecreated => $userdate) {
            $deltacount = abs($rawrecords[$timecreated.'filedir']->count -
                $rawrecords[$timecreated.OBJECT_LOCATION_LOCAL]->count -
                $rawrecords[$timecreated.OBJECT_LOCATION_DUPLICATED]->count);
            $deltasize = abs($rawrecords[$timecreated.'filedir']->size -
                $rawrecords[$timecreated.OBJECT_LOCATION_LOCAL]->size -
                $rawrecords[$timecreated.OBJECT_LOCATION_DUPLICATED]->size);
            $row['date'] = $userdate;
            if ($this->is_downloading() && in_array($this->download, ['csv', 'excel', 'json', 'ods'])) {
                $row['local_count'] = $rawrecords[$timecreated.OBJECT_LOCATION_LOCAL]->count;
                $row['local_size'] = $rawrecords[$timecreated.OBJECT_LOCATION_LOCAL]->size;
                $row['duplicated_count'] = $rawrecords[$timecreated.OBJECT_LOCATION_DUPLICATED]->count;
                $row['duplicated_size'] = $rawrecords[$timecreated.OBJECT_LOCATION_DUPLICATED]->size;
                $row['external_count'] = $rawrecords[$timecreated.OBJECT_LOCATION_EXTERNAL]->count;
                $row['external_size'] = $rawrecords[$timecreated.OBJECT_LOCATION_EXTERNAL]->size;
                $row['missing_count'] = $rawrecords[$timecreated.OBJECT_LOCATION_ERROR]->count;
                $row['missing_size'] = $rawrecords[$timecreated.OBJECT_LOCATION_ERROR]->size;
                $row['total_count'] = $rawrecords[$timecreated.'total']->count;
                $row['total_size'] = $rawrecords[$timecreated.'total']->size;
                $row['filedir_count'] = $rawrecords[$timecreated.'filedir']->count;
                $row['filedir_size'] = $rawrecords[$timecreated.'filedir']->size;
                $row['delta_count'] = $deltacount;
                $row['delta_size'] = $deltasize;
            } else {
                $row['local_count'] = number_format($rawrecords[$timecreated.OBJECT_LOCATION_LOCAL]->count);
                $row['local_size'] = display_size($rawrecords[$timecreated.OBJECT_LOCATION_LOCAL]->size);
                $row['duplicated_count'] = number_format($rawrecords[$timecreated.OBJECT_LOCATION_DUPLICATED]->count);
                $row['duplicated_size'] = display_size($rawrecords[$timecreated.OBJECT_LOCATION_DUPLICATED]->size);
                $row['external_count'] = number_format($rawrecords[$timecreated.OBJECT_LOCATION_EXTERNAL]->count);
                $row['external_size'] = display_size($rawrecords[$timecreated.OBJECT_LOCATION_EXTERNAL]->size);
                $row['missing_count'] = number_format($rawrecords[$timecreated.OBJECT_LOCATION_ERROR]->count);
                $row['missing_size'] = display_size($rawrecords[$timecreated.OBJECT_LOCATION_ERROR]->size);
                $row['total_count'] = number_format($rawrecords[$timecreated.'total']->count);
                $row['total_size'] = display_size($rawrecords[$timecreated.'total']->size);
                $row['filedir_count'] = number_format($rawrecords[$timecreated.'filedir']->count);
                $row['filedir_size'] = display_size($rawrecords[$timecreated.'filedir']->size);
                $row['delta_count'] = number_format($deltacount);
                $row['delta_size'] = display_size($deltasize);
            }
            $this->rawdata[] = $row;
        }
    }
}
