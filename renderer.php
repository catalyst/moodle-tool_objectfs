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
 * @package   tool_sssfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_sssfs\renderables\sss_file_status;
use tool_sssfs\report\sss_report;

defined('MOODLE_INTERNAL') || die();

class tool_sssfs_renderer extends plugin_renderer_base {

    protected function render_sss_file_status(sss_file_status $filestatus) {
        $output = '';

        // Could refactor this to have less duplication, but requirements may change for data.
        $locationreport = $filestatus->get_report(SSSFS_REPORT_FILE_LOCATION);
        $logsizereport = $filestatus->get_report(SSSFS_REPORT_LOG_SIZE);
        $mimetypereport = $filestatus->get_report(SSSFS_REPORT_MIME_TYPE);

        $lastrun = sss_report::get_last_task_runtime();

        if ($locationreport) {
            $output .= $this->render_file_location_report($locationreport, $output);
        }

        if ($logsizereport) {
            $output .= $this->render_log_size_report($logsizereport);
        }

        if ($mimetypereport) {
            $output .= $this->render_mime_type_report($mimetypereport);
        }

        if ($lastrun) {
            $labeltext = get_string('file_status:last_run', 'tool_sssfs', userdate($lastrun));
        } else {
            $labeltext = get_string('file_status:never_run', 'tool_sssfs');
        }

        $output .= html_writer::label($labeltext, null);

        return $output;
    }

    private function render_mime_type_report($mimetypereport) {
        $table = new html_table();

        $table->head = array('mimetype',
                             get_string('file_status:files', 'tool_sssfs'),
                             get_string('file_status:size', 'tool_sssfs'));

        foreach ($mimetypereport as $record) {
            $filesum = $record->filesum / 1024 / 1024; // Convert to MB.
            $filesum = round($filesum, 2);
            $table->data[] = array($record->key, $record->filecount, $filesum);
        }

        $output = html_writer::table($table);

        return $output;
    }

    private function render_log_size_report($logsizereport) {
        $table = new html_table();

        $table->head = array('logsize',
                             get_string('file_status:files', 'tool_sssfs'),
                             get_string('file_status:size', 'tool_sssfs'));

        foreach ($logsizereport as $record) {
            $filesum = $record->filesum / 1024 / 1024; // Convert to MB.
            $filesum = round($filesum, 2);
            $table->data[] = array($record->key, $record->filecount, $filesum);
        }

        $output = html_writer::table($table);

        return $output;
    }

    private function render_file_location_report($locationreport) {
        $table = new html_table();

        $table->head = array(get_string('file_status:location', 'tool_sssfs'),
                             get_string('file_status:files', 'tool_sssfs'),
                             get_string('file_status:size', 'tool_sssfs'));

        foreach ($locationreport as $record) {
            $filesum = $record->filesum / 1024 / 1024; // Convert to MB.
            $filesum = round($filesum, 2);
            $filestate = $this->get_file_state_string($record->key);
            $table->data[] = array($filestate, $record->filecount, $filesum);
        }

        $output = html_writer::table($table);

        return $output;
    }

    private function get_file_state_string($filestate) {
        switch ($filestate){
            case SSS_FILE_LOCATION_LOCAL:
                return get_string('file_status:state:local', 'tool_sssfs');
            case SSS_FILE_LOCATION_DUPLICATED:
                return get_string('file_status:state:duplicated', 'tool_sssfs');
            case SSS_FILE_LOCATION_EXTERNAL:
                return get_string('file_status:state:external', 'tool_sssfs');
            default;
                return get_string('file_status:state:unknown', 'tool_sssfs');
        }
    }
}