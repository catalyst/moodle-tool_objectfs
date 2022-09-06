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

    /** @var int $maxcount */
    protected $maxcount = 0;

    /** @var int $maxsize */
    protected $maxsize = 0;

    /** @var int $totalsize */
    protected $totalsize = 0;

    /**
     * Constructor for the file status history table.
     */
    public function __construct($reporttype, $reportid) {
        parent::__construct('statushistory');

        $this->reporttype = $reporttype;
        $this->reportid = $reportid;

        $columnheaders = [
            'heading'     => get_string('object_status:' . $reporttype, 'tool_objectfs'),
            'count'       => get_string('object_status:count', 'tool_objectfs'),
            'size'        => get_string('object_status:size', 'tool_objectfs'),
        ];

        if ($this->reporttype == 'log_size') {
            $columnheaders['runningsize'] = get_string('object_status:runningsize', 'tool_objectfs');
        }

        $this->set_attribute('class', 'table-sm');
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
        switch ($this->reporttype) {
            case 'mime_type':
                $sort = 'size ASC';
                break;

            case 'location':
            case 'log_size':
            default:
                $sort = 'heading ASC';
        }
        $params = array('reporttype' => $this->reporttype, 'reportid' => $this->reportid);
        $fields = 'datakey AS heading, objectcount AS count, objectsum AS size';
        $rows = $DB->get_records('tool_objectfs_report_data', $params, $sort, $fields);
        $this->rawdata = $rows;

        foreach ($rows as $row) {
            $this->totalsize += $row->size;
            if ($row->count > $this->maxcount) {
                $this->maxcount = $row->count;
            }
            if ($row->size > $this->maxsize) {
                $this->maxsize = $row->size;
            }
        }
    }

    /**
     * Format the heading column.
     *
     * @param  \stdClass $row
     * @return string
     * @throws \coding_exception
     */
    public function col_heading(\stdClass $row) {
        switch ($this->reporttype) {
            case 'location':
                $heading = $this->get_file_location_string($row->heading);
                break;

            case 'log_size':
                $heading = $this->get_size_range_from_logsize($row->heading);
                break;

            default:
                $heading = $row->heading;
        }
        return $heading;
    }

    /**
     * Format the count column.
     *
     * @param  \stdClass $row
     * @return string
     */
    public function col_count(\stdClass $row) {
        return $this->add_barchart($row->count, $this->maxcount, 'count');
    }

    /**
     * Format the size column.
     *
     * @param  \stdClass $row
     * @return string
     */
    public function col_size(\stdClass $row) {
        // For orphaned entries, the filesize is N/A or Unknown. Note: non-strict check as the heading is a string.
        if ($row->heading == OBJECT_LOCATION_ORPHANED) {
            return get_string('object_status:location:orphanedsizeunknown', 'tool_objectfs');
        }
        return $this->add_barchart($row->size, $this->maxsize, 'size');
    }

    /**
     * Format the column with running total (size) for log size report.
     *
     * @param  \stdClass $row
     * @return string
     */
    public function col_runningsize(\stdClass $row) {
        $runningsize = 0;
        foreach ($this->rawdata as $rawdatum) {
            $runningsize += $rawdatum->size;
            if ($rawdatum->heading == $row->heading) {
                break;
            }
        }
        return $this->add_barchart($runningsize, $this->totalsize, 'runningsize', 2);
    }

    /**
     * Wrap the column value into HTML tag with bar chart.
     *
     * @param  int    $value     Table cell value
     * @param  int    $max       Maximum value for a given column
     * @param  string $type      Column type (count, size or runningsize)
     * @param  int    $precision The optional number of decimal digits to round to
     * @return string
     */
    public function add_barchart($value, $max, $type, $precision = 0) {
        $share = 0;
        if ($max > 0) {
            $share = round(100 * $value / $max, $precision);
        }
        $htmlparams = array('class' => 'ofs-bar', 'style' => 'width:'.$share.'%');

        switch ($type) {
            case 'count':
                $output = \html_writer::tag('div', number_format($value), $htmlparams);
                break;

            case 'size':
                $output = \html_writer::tag('div', display_size($value), $htmlparams);
                break;

            case 'runningsize':
                $text = number_format($share, $precision). '% (' . display_size($value) . ')';
                $output = \html_writer::tag('div', $text, $htmlparams);
                break;

            default:
                $output = $value;
        }
        return $output;
    }

    /**
     * Formats location string.
     *
     * @param  int|string $filelocation
     * @return string
     * @throws \coding_exception
     */
    public function get_file_location_string($filelocation) {
        $locationstringmap = [
            'total' => 'object_status:location:total',
            'filedir' => 'object_status:filedir',
            'deltaa' => 'object_status:delta:a',
            'deltab' => 'object_status:delta:b',
            OBJECT_LOCATION_ERROR => 'object_status:location:error',
            OBJECT_LOCATION_LOCAL => 'object_status:location:local',
            OBJECT_LOCATION_DUPLICATED => 'object_status:location:duplicated',
            OBJECT_LOCATION_EXTERNAL => 'object_status:location:external',
            OBJECT_LOCATION_ORPHANED => 'object_status:location:orphaned',
        ];
        if (isset($locationstringmap[$filelocation])) {
            return get_string($locationstringmap[$filelocation], 'tool_objectfs');
        }
        return get_string('object_status:location:unknown', 'tool_objectfs');
    }

    /**
     * Formats size range from log size.
     *
     * @param  string $logsize
     * @return string
     */
    public function get_size_range_from_logsize($logsize) {
        // Small logsizes have been compressed.
        if ($logsize == 'small' || $logsize == 1) {
            return '< ' . display_size(1024);
        }
        $floor = pow(2, $logsize);
        $roof = ($floor * 2);
        $floor = display_size($floor);
        $roof = display_size($roof);
        $sizerange = "$floor - $roof";
        return $sizerange;
    }
}
