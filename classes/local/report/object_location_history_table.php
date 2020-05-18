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
            'date'        => 'date',
            'local_count' => 'Local (count)',
            'local_size'  => 'Local (size)',
            'duplicated_count' => 'Duplicated (count)',
            'duplicated_size'  => 'Duplicated (size)',
            'external_count' => 'External (count)',
            'external_size'  => 'External (size)',
            'missing_count' => 'Error (count)',
            'missing_size'  => 'Error (size)',
            'total_count' => 'Total (count)',
            'total_size'  => 'Total (size)',
            'filedir_count' => 'Filedir (count)',
            'filedir_size'  => 'Filedir (size)',
            'delta_count' => 'Delta (count)',
            'delta_size'  => 'Delta (size)',
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
        $rawrecords = $DB->get_records_sql($sql, $params);

        $reportdates = objectfs_report::get_report_dates();

        foreach ($reportdates as $timecreated => $userdate) {
            $row['date'] = $userdate;
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
            if (isset($rawrecords[$timecreated.'deltaa'])) {
                $delta = $rawrecords[$timecreated.'deltaa'];
            } else {
                $delta = $rawrecords[$timecreated.'deltab'];
            }
            $row['delta_count'] = number_format($delta->count);
            $row['delta_size'] = display_size($delta->size);

            // TODO: Add bar chart.
            $this->rawdata[] = $row;
        }
    }
}