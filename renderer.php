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
     * @param  array  $reports     Report ids and dates array
     * @param  int    $reportid    Requested report id
     *
     * @return string HTML string
     * @throws /moodle_exception
     */
    public function object_status_history_page_header($reports, $reportid) {
        global $OUTPUT;
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
        $output .= $OUTPUT->box_start();
        $output .= $OUTPUT->single_button(new \moodle_url($baseurl, $oldestid), '<<', 'get', $prevdisabled);
        $output .= $OUTPUT->spacer();
        $output .= $OUTPUT->single_button(new \moodle_url($baseurl, $previd), '<', 'get', $prevdisabled);
        $output .= $OUTPUT->spacer();
        $output .= $OUTPUT->single_select(new \moodle_url($baseurl), 'reportid', $userdates, $reportid, false);
        $output .= $OUTPUT->spacer();
        $output .= $OUTPUT->single_button(new \moodle_url($baseurl, $nextid), '>', 'get', $nextdisabled);
        $output .= $OUTPUT->spacer();
        $output .= $OUTPUT->single_button(new \moodle_url($baseurl, $latestid), '>>', 'get', $nextdisabled);
        $output .= $OUTPUT->box_end();

        return $output;
    }
}
