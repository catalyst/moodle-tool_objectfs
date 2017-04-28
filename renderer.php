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
 * File status renderer.
 *
 * @package   tool_objectfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_objectfs\report\objectfs_report;

defined('MOODLE_INTERNAL') || die();

class tool_objectfs_renderer extends plugin_renderer_base {

    public function render_objectfs_report(objectfs_report $report) {
        $reporttype = $report->get_report_type();

        $renderfunction = "render_{$reporttype}_report";

        $output = '';

        $output .= $this->$renderfunction($report);

        return $output;
    }

    private function render_location_report($report) {
        $rows = $report->get_rows();

        if (empty($rows)) {
            return '';
        }

        $table = new html_table();

        $table->head = array(get_string('object_status:location', 'tool_objectfs'),
                             get_string('object_status:files', 'tool_objectfs'),
                             get_string('object_status:size', 'tool_objectfs'));

        foreach ($rows as $row) {
            $filelocation = $this->get_file_location_string($row->datakey); // Turn int location into string.
            $table->data[] = array($filelocation, $row->objectcount, $row->objectsum);
        }

        $this->augment_barchart($table);

        $output = html_writer::table($table);

        return $output;
    }

    private function get_file_location_string($filelocation) {
        if ($filelocation == 'total') {
            return get_string('object_status:location:total', 'tool_objectfs');
        }
        switch ($filelocation){
            case OBJECT_LOCATION_ERROR:
                return get_string('object_status:location:error', 'tool_objectfs');
            case OBJECT_LOCATION_LOCAL:
                return get_string('object_status:location:local', 'tool_objectfs');
            case OBJECT_LOCATION_DUPLICATED:
                return get_string('object_status:location:duplicated', 'tool_objectfs');
            case OBJECT_LOCATION_EXTERNAL:
                return get_string('object_status:location:external', 'tool_objectfs');
            default:
                return get_string('object_status:location:unknown', 'tool_objectfs');
        }
    }

    private function render_log_size_report($report) {
        $rows = $report->get_rows();

        if (empty($rows)) {
            return '';
        }

        $table = new html_table();

        $table->head = array('logsize',
                             get_string('object_status:files', 'tool_objectfs'),
                             get_string('object_status:size', 'tool_objectfs'));

        foreach ($rows as $row) {
            $sizerange = $this->get_size_range_from_logsize($row->datakey); // Turn logsize into a byte range.
            $table->data[] = array($sizerange, $row->objectcount, $row->objectsum);
        }

        $this->augment_barchart($table);

        $output = html_writer::table($table);

        return $output;
    }

    private function get_size_range_from_logsize($logsize) {

        // Small logsizes have been compressed.
        if ($logsize == 'small') {
            return '< 1MB';
        }

        $floor = pow(2, $logsize);
        $roof = ($floor * 2);
        $floor = display_size($floor);
        $roof = display_size($roof);
        $sizerange = "$floor - $roof";
        return $sizerange;
    }

    private function render_mime_type_report($report) {
        $rows = $report->get_rows();

        if (empty($rows)) {
            return '';
        }

        $table = new html_table();

        $table->head = array('mimetype',
                             get_string('object_status:files', 'tool_objectfs'),
                             get_string('object_status:size', 'tool_objectfs'));

        foreach ($rows as $row) {
            $table->data[] = array($row->datakey, $row->objectcount, $row->objectsum);
        }

        $this->augment_barchart($table);

        $output = html_writer::table($table);

        return $output;
    }

    private function augment_barchart(&$table) {

        // This assumes 2 columns, the first is a number and the second
        // is a file size.

        foreach (array(1, 2) as $col) {

            $max = 0;
            foreach ($table->data as $row) {
                if ($row[$col] > $max) {
                    $max = $row[$col];
                }
            }

            foreach ($table->data as $i => $row) {
                $table->data[$i][$col] = sprintf('<div class="ofs-bar" style="width:%.1f%%">%s</div>',
                    100 * $row[$col] / $max,
                    $col == 1 ? number_format($row[$col]) : display_size($row[$col])
                );
            }
        }
    }

    public function object_status_page_intro() {
        $output = '';

        $url = new \moodle_url('/admin/tool/objectfs/index.php');
        $urltext = get_string('settings', 'tool_objectfs');
        $output .= html_writer::tag('div', html_writer::link($url , $urltext));

        $config = get_objectfs_config();
        if (!isset($config->enabletasks) || !$config->enabletasks) {
            $output .= $this->box(get_string('not_enabled', 'tool_objectfs'));
        }

        $lastrun = objectfs_report::get_last_generate_status_report_runtime();
        if ($lastrun) {
            $lastruntext = get_string('object_status:last_run', 'tool_objectfs', userdate($lastrun));
        } else {
            $lastruntext = get_string('object_status:never_run', 'tool_objectfs');
        }
        $output .= $this->box($lastruntext);

        // Adds bar chart styling for sizes and counts.
        $output .= "<style>.ofs-bar { background: #17a5eb; white-space: nowrap; }</style>";

        return $output;
    }
}
