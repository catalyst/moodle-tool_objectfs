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
 * File status history table.
 *
 * @package   tool_objectfs
 * @author    Mikhail Golenkov <golenkovm@gmail.com>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\local\report;

defined('MOODLE_INTERNAL') || die();

use tool_objectfs\local\report\objectfs_report;

require_once($CFG->libdir . '/tablelib.php');

/**
 * Table to display file status history.
 *
 * @author     Mikhail Golenkov <golenkovm@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class object_status_history_table extends \table_sql {

    /** @var string $reporttype */
    protected $reporttype = '';

    /** @var int $reportid */
    protected $reportid = 0;

    /**
     * Constructor for the file status history table.
     */
    public function __construct($reporttype, $reportid) {
        parent::__construct('statushistory');

        $this->reporttype = $reporttype;
        $this->reportid = $reportid;

        $columnheaders = [
            'reporttype'  => get_string('object_status:' . $reporttype, 'tool_objectfs'),
            'files'       => get_string('object_status:files', 'tool_objectfs'),
            'size'        => get_string('object_status:size', 'tool_objectfs'),
        ];

        $this->define_columns(array_keys($columnheaders));
        $this->define_headers(array_values($columnheaders));
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
        $params = array('reporttype' => $this->reporttype, 'reportid' => $this->reportid);
        $fields = 'datakey AS reporttype, objectcount AS files, objectsum AS size';
        $rows = $DB->get_records('tool_objectfs_report_data', $params, '', $fields);

        $table = new \stdClass();
        foreach ($rows as $row) {
            if ($this->reporttype == 'location') {
                $reporttype = objectfs_report::get_file_location_string($row->reporttype);
            } else if ($this->reporttype == 'log_size') {
                $reporttype = objectfs_report::get_size_range_from_logsize($row->reporttype);
            } else {
                $reporttype = $row->reporttype;
            }

            $table->data[] = array($reporttype, $row->files, $row->size);
        }
        objectfs_report::augment_barchart($table);
        foreach ($table->data as $item) {
            $this->rawdata[] = array('reporttype' => $item[0], 'files' => $item[1], 'size' => $item[2]);
        }
    }
}