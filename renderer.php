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

class tool_objectfs_renderer extends plugin_renderer_base {

    /**
     * Returns a header for Object status history page.
     *
     * @param  array  $reports     Report ids and dates array
     * @param  int    $reportid    Requested report id
     *
     * @return string HTML string
     * @throws /moodle_exception
     */
    public function object_status_history_page_header($reports, $reportid) {
        $output = '';

        $baseurl = '/admin/tool/objectfs/object_status.php';

        $previd = array();
        $nextid = array();
        $prevdisabled = array('disabled' => true);
        $nextdisabled = array('disabled' => true);

        end($reports);
        $oldestid = array('reportid' => key($reports));
        reset($reports);
        $latestid = array('reportid' => key($reports));

        while ($reportid != key($reports)) {
            next($reports);
        }

        if (next($reports)) {
            $previd = ['reportid' => key($reports)];
            $prevdisabled = array();
            prev($reports);
        } else {
            end($reports);
        }

        if (prev($reports)) {
            $nextid = ['reportid' => key($reports)];
            $nextdisabled = array();
            next($reports);
        } else {
            reset($reports);
        }

        foreach ($reports as $id => $timestamp) {
            $userdates[$id] = userdate($timestamp, get_string('strftimedaydatetime'));
        }
        $output .= $this->output->box_start();
        $output .= $this->output->single_button(new \moodle_url($baseurl, $oldestid), '<<', 'get', $prevdisabled);
        $output .= $this->output->spacer();
        $output .= $this->output->single_button(new \moodle_url($baseurl, $previd), '<', 'get', $prevdisabled);
        $output .= $this->output->spacer();
        $output .= $this->output->single_select(new \moodle_url($baseurl), 'reportid', $userdates, $reportid, false);
        $output .= $this->output->spacer();
        $output .= $this->output->single_button(new \moodle_url($baseurl, $nextid), '>', 'get', $nextdisabled);
        $output .= $this->output->spacer();
        $output .= $this->output->single_button(new \moodle_url($baseurl, $latestid), '>>', 'get', $nextdisabled);
        $output .= $this->output->box_end();

        return $output;
    }
}
