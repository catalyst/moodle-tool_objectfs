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

defined('MOODLE_INTERNAL') || die();

class tool_sssfs_renderer extends plugin_renderer_base {

    protected function render_sss_file_status(sss_file_status $filestatus) {
        $output = '';

        $table = new html_table();

        $table->head = array(get_string('file_status:location', 'tool_sssfs'),
                             get_string('file_status:files', 'tool_sssfs'),
                             get_string('file_status:size', 'tool_sssfs'));

        foreach ($filestatus->statusdata as $filestate => $filetypedata) {
            $size = $filetypedata->filesum / 1024 / 1024; // Convert to MB.
            $size = round($size, 2);
            $filestate = $this->get_file_state_string($filestate);
            $table->data[] = array($filestate, $filetypedata->filecount, $size);
        }

        $output .= html_writer::table($table);

        return $output;
    }

    private function get_file_state_string($filestate) {
        switch ($filestate){
            case SSS_FILE_STATE_LOCAL:
                return get_string('file_status:state:local', 'tool_sssfs');
            case SSS_FILE_STATE_DUPLICATED:
                return get_string('file_status:state:duplicated', 'tool_sssfs');
            case SSS_FILE_STATE_EXTERNAL:
                return get_string('file_status:state:external', 'tool_sssfs');
            default;
                return get_string('file_status:state:unknown', 'tool_sssfs');
        }
    }
}