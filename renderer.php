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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.\

/**
 * File status renderer.
 *
 * @package   tool_sssfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class tool_sssfs_renderer extends plugin_renderer_base {
    protected function render_sss_file_status(tool_sssfs\sss_file_status $filestatus) {
        $output = '';

        $table = new html_table();
        // TODO: Make these lang strings.
        // TODO: Convert size to readable format.
        $table->head = array('File location', 'Files', 'Total size');

        foreach ($filestatus->statusdata as $filetype  => $filetypedata) {
            $table->data[] = array($filetype, $filetypedata->filecount, $filetypedata->filesum);
        }
        $output .= html_writer::table($table);

        return $output;
    }


}