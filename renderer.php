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

use tool_objectfs\renderable\object_status;
use tool_objectfs\report\object_report;

defined('MOODLE_INTERNAL') || die();

class tool_objectfs_renderer extends plugin_renderer_base {

    protected function render_object_status(object_status $filestatus) {
        $output = '';

        $output .= \html_writer::link(new \moodle_url('/admin/tool/objectfs/index.php'), get_string('settings', 'tool_objectfs'));
        $output .= \html_writer::start_tag('br');
        $output .= "<style>.ofs-bar { background: #17a5eb; white-space: nowrap; }</style>";

        $config = get_objectfs_config();

        if (!isset($config->enabletasks) || !$config->enabletasks) {
            $labeltext = get_string('not_enabled', 'tool_objectfs');
            $output .= html_writer::label($labeltext, null);
            $output .= \html_writer::start_tag('br');
        }

        // Could refactor this to have less duplication, but requirements may change for data.
        $locationreport = $filestatus->get_report(OBJECTFS_REPORT_OBJECT_LOCATION);
        $logsizereport = $filestatus->get_report(OBJECTFS_REPORT_LOG_SIZE);
        $mimetypereport = $filestatus->get_report(OBJECTFS_REPORT_MIME_TYPE);

        $lastrun = object_report::get_last_task_runtime();

        if ($locationreport) {
            $output .= $this->render_object_location_report($locationreport, $output);
        }

        if ($logsizereport) {
            $output .= $this->render_log_size_report($logsizereport);
        }

        if ($mimetypereport) {
            $output .= $this->render_mime_type_report($mimetypereport);
        }

        if ($lastrun) {
            $labeltext = get_string('object_status:last_run', 'tool_objectfs', userdate($lastrun));
        } else {
            $labeltext = get_string('object_status:never_run', 'tool_objectfs');
        }

        $output .= html_writer::label($labeltext, null);

        return $output;
    }

    private function augment_barchart(&$table) {

        // This assumes 2 columns, the first is a number and the second
        // is a file size.

        foreach (array(1,2) as $col) {

            $max = 0;
            foreach ($table->data as $row) {
                if ($row[$col] > $max) {
                    $max = $row[$col];
                }
            }

            foreach ($table->data as $i => $row) {
                $table->data[$i][$col] = sprintf('<div class="ofs-bar" style="width:%.1f%%">%s</div>',
                    100 * $row[$col] / $max,
                    $col == 1 ? $row[$col] : display_size($row[$col])
                );
            }
        }
    }

    private function render_mime_type_report($mimetypereport) {
        $table = new html_table();

        $table->head = array('mimetype',
                             get_string('object_status:files', 'tool_objectfs'),
                             get_string('object_status:size', 'tool_objectfs'));

        foreach ($mimetypereport as $record) {
            $table->data[] = array($record->datakey, $record->objectcount, $record->objectsum);
        }
        $this->augment_barchart($table);

        $output = html_writer::table($table);

        return $output;
    }

    private function render_log_size_report($logsizereport) {
        $table = new html_table();

        $table->head = array('logsize',
                             get_string('object_status:files', 'tool_objectfs'),
                             get_string('object_status:size', 'tool_objectfs'));

        foreach ($logsizereport as $record) {
            $table->data[] = array($record->datakey, $record->objectcount, $record->objectsum);
        }
        $this->augment_barchart($table);

        $output = html_writer::table($table);

        return $output;
    }

    private function render_object_location_report($locationreport) {
        $table = new html_table();

        $table->head = array(get_string('object_status:location', 'tool_objectfs'),
                             get_string('object_status:files', 'tool_objectfs'),
                             get_string('object_status:size', 'tool_objectfs'));

        foreach ($locationreport as $record) {
            $filelocation = $this->get_file_location_string($record->datakey);
            $table->data[] = array($filelocation, $record->objectcount, $record->objectsum);
        }
        $this->augment_barchart($table);

        $output = html_writer::table($table);

        return $output;
    }

    private function get_file_location_string($filelocation) {
        switch ($filelocation){
            case OBJECT_LOCATION_ERROR:
                return get_string('object_status:location:error', 'tool_objectfs');
            case OBJECT_LOCATION_LOCAL:
                return get_string('object_status:location:local', 'tool_objectfs');
            case OBJECT_LOCATION_DUPLICATED:
                return get_string('object_status:location:duplicated', 'tool_objectfs');
            case OBJECT_LOCATION_REMOTE:
                return get_string('object_status:location:external', 'tool_objectfs');
            default;
                return get_string('object_status:location:unknown', 'tool_objectfs');
        }
    }
}
