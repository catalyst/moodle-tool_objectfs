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

use tool_objectfs\local\manager;
use tool_objectfs\local\report\objectfs_report;
use tool_objectfs\local\store\object_file_system;

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

    /**
     * @return string
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    private function get_generate_status_report_update_stats_link() {
        $classname = '\tool_objectfs\task\generate_status_report';
        if (class_exists('tool_task\run_from_cli')) {
            $runnabletasks = tool_task\run_from_cli::is_runnable();
        } else {
            $runnabletasks = false;
        }

        $task = \core\task\manager::get_scheduled_task($classname);
        if (!$task->get_disabled() && get_config('tool_task', 'enablerunnow') && $runnabletasks) {

            $link = html_writer::link(
                new moodle_url(
                    '/admin/tool/task/schedule_task.php',
                    ['task' => '\tool_objectfs\task\generate_status_report']
                ),
                '<small>' . get_string('object_status:filedir:update', 'tool_objectfs') . '</small>'
            );
            return " ($link)";
        }
        return '';
    }

    /**
     * @param int|string $filelocation
     * @return string
     * @throws coding_exception
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
        ];
        if (isset($locationstringmap[$filelocation])) {
            return get_string($locationstringmap[$filelocation], 'tool_objectfs');
        }
        return get_string('object_status:location:unknown', 'tool_objectfs');
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

    public function get_size_range_from_logsize($logsize) {

        // Small logsizes have been compressed.
        if ($logsize == 'small') {
            return '< 1KB';
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

    public function augment_barchart(&$table) {

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

        $url = new \moodle_url('/admin/settings.php?section=tool_objectfs');
        $urltext = get_string('settings', 'tool_objectfs');
        $output .= html_writer::tag('div', html_writer::link($url , $urltext));

        $config = manager::get_objectfs_config();
        if (!isset($config->enabletasks) || !$config->enabletasks) {
            $output .= $this->box(get_string('not_enabled', 'tool_objectfs'));
        }

        $lastrun = objectfs_report::get_last_generate_status_report_runtime();
        if ($lastrun) {
            $lastruntext = get_string('object_status:last_run', 'tool_objectfs', userdate($lastrun));
        } else {
            $lastruntext = get_string('object_status:never_run', 'tool_objectfs');
        }
        $lastruntext .= $this->get_generate_status_report_update_stats_link();
        $output .= $this->box($lastruntext);

        return $output;
    }

    /**
     * Delete test files from files table
     * @throws coding_exception
     * @throws dml_exception
     */
    public function delete_presignedurl_tests_files() {
        $filestorage = get_file_storage();
        $filesarea = $filestorage->get_area_files(
            \context_system::instance()->id,
            OBJECTFS_PLUGIN_NAME,
            'settings',
            0
        );
        foreach ($filesarea as $testfile) {
            if ('.' === $testfile->get_filename()) {
                continue;
            }
            $testfile->delete();
        }
    }

    public function presignedurl_tests_load_files($fs) {
        global $CFG;
        $filestorage = get_file_storage();
        $fixturespath = $CFG->dirroot.'/admin/tool/objectfs/tests/fixtures/';
        $fixturesfiles = glob($fixturespath.'*');
        $syscontext = \context_system::instance();

        foreach ($fixturesfiles as $fixturesfile) {
            // Filter out possible compressed files.
            if (false !== strpos($fixturesfile, '.br')) {
                continue;
            }
            $testfilename = str_replace($fixturespath, '', $fixturesfile);

            $contextid = $syscontext->id;
            $component = 'tool_objectfs';
            $filearea = 'settings';
            $itemid = 0;
            $filepath = '/';

            $filerecord = array(
                'contextid' => $contextid,
                'component' => $component,
                'filearea'  => $filearea,
                'itemid'    => $itemid,
                'filepath'  => $filepath,
                'filename'  => $testfilename
            );

            $testfile = $filestorage->get_file($contextid, $component, $filearea, $itemid, $filepath, $testfilename);
            if (!$testfile) {
                $testfile = $filestorage->create_file_from_pathname($filerecord, $fixturesfile);
            }

            $contenthash = $testfile->get_contenthash();
            $readable = $fs->is_file_readable_externally_by_hash($contenthash);
            if (!$readable) {
                $fs->copy_from_local_to_external($contenthash);
            }
            $testfiles[] = $testfile;
        }

        return $testfiles;
    }

    public function presignedurl_tests_content($fs, $testfiles) {
        global $CFG;
        $CFG->enablepresignedurls = true;
        $output = '';

        $output .= $this->box('');
        $output .= $this->heading(get_string('presignedurl_testing:test1', 'tool_objectfs'), 4);
        foreach ($testfiles as $file) {
            $presignedurl = $this->generate_file_url($file, false, true);
            $output .= $this->heading($this->get_output($fs, $presignedurl, $file, 'downloadfile'), 5);
        }

        $output .= $this->box('');
        $output .= $this->heading(get_string('presignedurl_testing:test2', 'tool_objectfs'), 4);
        foreach ($testfiles as $file) {
            $presignedurl = $this->generate_file_url($file, false, true);

            $output .= $this->heading($this->get_output($fs, $presignedurl, $file, 'downloadfile'), 5);
        }

        $output .= $this->box('');
        $output .= $this->heading(get_string('presignedurl_testing:test3', 'tool_objectfs'), 4);
        foreach ($testfiles as $file) {
            $presignedurl = $this->generate_file_url($file);

            $output .= $this->heading($this->get_output($fs, $presignedurl, $file, 'openinbrowser'), 5);
        }

        $output .= $this->box('');
        $output .= $this->heading(get_string('presignedurl_testing:test4', 'tool_objectfs'), 4);
        foreach ($testfiles as $file) {
            $presignedurl = $this->generate_file_url($file);

            $outputstring = '"'.$file->get_filename().'" '.get_string('presignedurl_testing:fileiniframe', 'tool_objectfs').':';
            $output .= $this->heading($outputstring, 5);

            $output .= $this->box($this->get_output($fs, $presignedurl, $file, 'iframesnotsupported'));
            $output .= $this->box('');
        }

        $output .= $this->box('');
        $output .= $this->heading(get_string('presignedurl_testing:test5', 'tool_objectfs'), 4);
        // Expires in seconds.
        $testexpirefiles = ['testimage.png' => 0, 'testlarge.pdf' => 10, 'test.txt' => -1];
        foreach ($testfiles as $key => $file) {
            $filename = $file->get_filename();
            if (!isset($testexpirefiles[$filename])) {
                continue;
            }
            $presignedurl = $this->generate_file_url($file, $testexpirefiles[$filename]);

            $outputstring = '"' . $filename . '" '.
                get_string('presignedurl_testing:fileiniframe', OBJECTFS_PLUGIN_NAME) . ':';
            $output .= $this->heading($outputstring, 5);

            $output .= $this->box($this->get_output($fs, $presignedurl, $file, 'iframesnotsupported'));
            $output .= $this->box('');
        }

        return $output;
    }

    /**
     * Generate a file url with adding a param to set 'Expires' header.
     * @param stored_file $file
     * @param int|bool $expires
     * @param bool $forcedownload
     * @return string
     * @throws dml_exception
     */
    private function generate_file_url($file, $expires = false, $forcedownload = false) {
        $url = \moodle_url::make_pluginfile_url(
            \context_system::instance()->id,
            OBJECTFS_PLUGIN_NAME,
            'settings',
            0,
            '/',
            $file->get_filename(),
            $forcedownload
        );
        $expires = (-1 !== $expires) ? $expires : false;
        if (false !== $expires) {
            $url->param('expires', $expires);
        }
        return $url->out();
    }

    /**
     * Generates the output string that contains the presignedurl or local url.
     * @param object_file_system $fs
     * @param string $url
     * @param stored_file $file
     * @param string $identifier
     * @return string
     * @throws coding_exception
     */
    private function get_output($fs, $url, $file, $identifier) {
        global $OUTPUT;
        $redirect = $OUTPUT->pix_icon('i/grade_correct', '', 'moodle', ['class' => 'icon']) . 'Redirecting to external storage: ';
        if (!$fs->presigned_url_should_redirect($file->get_contenthash())) {
            $redirect = $OUTPUT->pix_icon('i/grade_incorrect', '', 'moodle', ['class' => 'icon']) . 'Not redirecting: ';
        }
        $output = get_string('presignedurl_testing:' . $identifier, 'tool_objectfs') . ': '.
            '<a href="'. $url .'">'. $file->get_filename() . '</a>';
        if ('iframesnotsupported' === $identifier) {
            $output = '<iframe height="400" width="100%" src="' . $url . '">'.
                get_string('presignedurl_testing:' . $identifier, 'tool_objectfs').'</iframe>';
        }
        return $output . '<br><small>' . $redirect . $url . '</small>';;
    }

    /**
     * Returns a header for Object status history page.
     *
     * @param  array  $dates       Report dates array
     * @param  int    $reportdate  Requested report date
     *
     * @return string HTML string
     * @throws /moodle_exception
     */
    public function object_status_history_page_header($dates, $reportdate) {
        global $OUTPUT;
        $output = '';

        $baseurl = '/admin/tool/objectfs/object_status_history.php';

        $prevdate = array();
        $nextdate = array();
        $prevdisabled = array('disabled' => true);
        $nextdisabled = array('disabled' => true);

        end($dates);
        $oldestdate = array('date' => key($dates));
        reset($dates);
        $latestdate = array('date' => key($dates));

        while ($reportdate != key($dates)) {
            next($dates);
        }

        if (next($dates)) {
            $prevdate = ['date' => key($dates)];
            $prevdisabled = array();
            prev($dates);
        } else {
            end($dates);
        }

        if (prev($dates)) {
            $nextdate = ['date' => key($dates)];
            $nextdisabled = array();
            next($dates);
        } else {
            reset($dates);
        }

        $output .= $OUTPUT->box_start();
        $output .= $OUTPUT->single_button(new \moodle_url($baseurl, $oldestdate), '<<', 'get', $prevdisabled);
        $output .= $OUTPUT->spacer();
        $output .= $OUTPUT->single_button(new \moodle_url($baseurl, $prevdate), '<', 'get', $prevdisabled);
        $output .= $OUTPUT->spacer();
        $output .= $OUTPUT->single_select(new \moodle_url($baseurl, array('date' => $reportdate)), 'date',
            $dates, $reportdate, false);
        $output .= $OUTPUT->spacer();
        $output .= $OUTPUT->single_button(new \moodle_url($baseurl, $nextdate), '>', 'get', $nextdisabled);
        $output .= $OUTPUT->spacer();
        $output .= $OUTPUT->single_button(new \moodle_url($baseurl, $latestdate), '>>', 'get', $nextdisabled);
        $output .= $OUTPUT->box_end();

        return $output;
    }
}
