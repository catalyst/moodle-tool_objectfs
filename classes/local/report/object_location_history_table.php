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
    /** @var array $local_sizes */
    public $localsizes = [];
    /** @var array $duplicatedsizes */
    public $duplicatedsizes = [];
    /** @var array $orphanedsizes */
    public $orphanedsizes = [];
    /** @var array $externalsizes */
    public $externalsizes = [];
    /** @var array $missingsizes */
    public $missingsizes = [];
    /** @var array $totalsizes */
    public $totalsizes = [];
    /** @var array $filedirsizes */
    public $filedirsizes = [];
    /** @var array $deltasizes */
    public $deltasizes = [];

    /** @var array dates */
    public $dates = [];
    /**
     * Constructor for the file location history table.
     */
    public function __construct() {
        parent::__construct('locationhistory');
        $columnheaders = [
            'date' => get_string('date'),
            'local_count' => get_string('object_status:location:localcount', 'tool_objectfs'),
            'duplicated_count' => get_string('object_status:location:duplicatedcount', 'tool_objectfs'),
            'orphaned_count' => get_string('object_status:location:orphanedcount', 'tool_objectfs'),
            'external_count' => get_string('object_status:location:externalcount', 'tool_objectfs'),
            'missing_count' => get_string('object_status:location:missingcount', 'tool_objectfs'),
            'total_count' => get_string('object_status:location:totalcount', 'tool_objectfs'),
            'filedir_count' => get_string('object_status:location:filedircount', 'tool_objectfs'),
            'delta_count' => get_string('object_status:location:deltacount', 'tool_objectfs'),
        ];
        $this->is_downloadable(true);
        $this->define_columns(array_keys($columnheaders));
        $this->define_headers(array_values($columnheaders));
        $this->collapsible(false);
        $this->sortable(false);
        $this->pageable(false);
    }

    /**
     * Build the table from the fetched data.
     */
    public function build_table() {
        if ($this->is_downloading()) {
            $columnheaders = [
                'date' => get_string('date'),
                'local_count' => get_string('object_status:location:localcount', 'tool_objectfs'),
                'local_size' => get_string('object_status:location:localsize', 'tool_objectfs'),
                'duplicated_count' => get_string('object_status:location:duplicatedcount', 'tool_objectfs'),
                'duplicated_size' => get_string('object_status:location:duplicatedsize', 'tool_objectfs'),
                'orphaned_count' => get_string('object_status:location:orphanedcount', 'tool_objectfs'),
                'orphaned_size' => get_string('object_status:location:orphanedsize', 'tool_objectfs'),
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
            $this->define_columns(array_keys($columnheaders));
            $this->define_headers(array_values($columnheaders));
        }
        parent::build_table();
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
        $fields = 'CONCAT(reportid, datakey) AS uid, datakey AS location, objectcount AS count, objectsum AS size';
        $conditions = ['reporttype' => 'location'];
        $rawrecords = $DB->get_records('tool_objectfs_report_data', $conditions, 'reportid', $fields);
        $reports = objectfs_report::get_report_ids();

        // Used to fallback to when the expected record is not there.
        // NOTE: This avoids the need to null coalesce on a non-existing count/size.
        $emptyrecord = (object)[
            'count' => 0,
            'size' => 0,
        ];
        foreach ($reports as $id => $timecreated) {
            // Initialises the records to be used, and fallback to an empty one if not found.
            $localrecord = $rawrecords[$id . OBJECT_LOCATION_LOCAL] ?? $emptyrecord;
            $duplicatedrecord = $rawrecords[$id . OBJECT_LOCATION_DUPLICATED] ?? $emptyrecord;
            $orphanedrecord = $rawrecords[$id . OBJECT_LOCATION_ORPHANED] ?? $emptyrecord;
            $externalrecord = $rawrecords[$id . OBJECT_LOCATION_EXTERNAL] ?? $emptyrecord;
            $errorrecord = $rawrecords[$id . OBJECT_LOCATION_ERROR] ?? $emptyrecord;
            $filedir = $rawrecords[$id . 'filedir'] ?? $emptyrecord;
            $total = $rawrecords[$id . 'total'] ?? $emptyrecord;

            $localcount = $localrecord->count + $duplicatedrecord->count;
            $deltacount = abs($filedir->count - $localcount);
            $localsize = $localrecord->size + $duplicatedrecord->size;
            $deltasize = abs($filedir->size - $localsize);
            $row['date'] = userdate($timecreated, get_string('strftimedaydatetime'));
            if ($this->is_downloading() && in_array($this->download, ['csv', 'excel', 'json', 'ods'])) {
                $row['local_count'] = $localrecord->count;
                $row['local_size'] = $localrecord->size;
                $row['duplicated_count'] = $duplicatedrecord->count;
                $row['duplicated_size'] = $duplicatedrecord->size;
                $row['orphaned_count'] = $orphanedrecord->count;
                $row['orphaned_size'] = get_string('object_status:location:orphanedsizeunknown', 'tool_objectfs');
                $row['external_count'] = $externalrecord->count;
                $row['external_size'] = $externalrecord->size;
                $row['missing_count'] = $errorrecord->count;
                $row['missing_size'] = $errorrecord->size;
                $row['total_count'] = $total->count;
                $row['total_size'] = $total->size;
                $row['filedir_count'] = $filedir->count;
                $row['filedir_size'] = $filedir->size;
                $row['delta_count'] = $deltacount;
                $row['delta_size'] = $deltasize;
            } else {
                $row['local_count'] = number_format($localrecord->count);
                $row['duplicated_count'] = number_format($duplicatedrecord->count);
                $row['orphaned_count'] = number_format($orphanedrecord->count);
                $row['external_count'] = number_format($externalrecord->count);
                $row['missing_count'] = number_format($errorrecord->count);
                $row['total_count'] = number_format($total->count);
                $row['filedir_count'] = number_format($filedir->count);
                $row['delta_count'] = number_format($deltacount);
                // Prepare data for chart.
                $this->localsizes[] = $this->size_to_mb($localrecord->size);
                $this->duplicatedsizes[] = $this->size_to_mb($duplicatedrecord->size);
                $this->orphanedsizes[] = $this->size_to_mb($orphanedrecord->size);
                $this->externalsizes[] = $this->size_to_mb($externalrecord->size);
                $this->missingsizes[] = $this->size_to_mb($errorrecord->size);
                $this->totalsizes[] = $this->size_to_mb($total->size);
                $this->filedirsizes[] = $this->size_to_mb($filedir->size);
                $this->deltasizes[] = $this->size_to_mb($deltasize);
                $this->dates[] = userdate($timecreated, get_string('strftimedatetime'));
            }
            $this->rawdata[] = $row;
        }
        $this->sort_chart_data();
    }

    /**
     * Sort data for displaying history chart.
     */
    private function sort_chart_data(): void {
        $this->dates = array_reverse($this->dates);
        $this->localsizes = array_reverse($this->localsizes);
        $this->duplicatedsizes = array_reverse($this->duplicatedsizes);
        $this->orphanedsizes = array_reverse($this->orphanedsizes);
        $this->externalsizes = array_reverse($this->externalsizes);
        $this->missingsizes = array_reverse($this->missingsizes);
        $this->totalsizes = array_reverse($this->totalsizes);
        $this->filedirsizes = array_reverse($this->filedirsizes);
        $this->deltasizes = array_reverse($this->deltasizes);
    }

    /**
     * Convert size in bytes to mb.
     *
     * @param int $size size of object.
     */
    private function size_to_mb(int $size): int {
        return intval($size / (1024 * 1024));
    }

    /**
     * Generate chart data from table.
     */
    public function get_chart_data() {
        return [
           'labels' => $this->dates,
           'series' => [
               ["label" => get_string('object_status:location:localsizechart', 'tool_objectfs'),
                   "data" => $this->localsizes],
               ["label" => get_string('object_status:location:duplicatedsizechart', 'tool_objectfs'),
                   "data" => $this->duplicatedsizes],
               ["label" => get_string('object_status:location:orphanedsizechart', 'tool_objectfs'),
                   "data" => $this->orphanedsizes],
               ["label" => get_string('object_status:location:externalsizechart', 'tool_objectfs'),
                   "data" => $this->externalsizes],
               ["label" => get_string('object_status:location:missingsizechart', 'tool_objectfs'),
                   "data" => $this->missingsizes],
               ["label" => get_string('object_status:location:totalsizechart', 'tool_objectfs'),
                   "data" => $this->totalsizes],
               ["label" => get_string('object_status:location:filedirsizechart', 'tool_objectfs'),
                   "data" => $this->filedirsizes],
               ["label" => get_string('object_status:location:deltasizechart', 'tool_objectfs'),
                   "data" => $this->deltasizes],
           ],
        ];
    }
}
