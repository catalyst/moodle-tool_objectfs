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
 * File status history page.
 *
 * @package   tool_objectfs
 * @author    Mikhail Golenkov <golenkovm@gmail.com>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/lib/adminlib.php');
require_once($CFG->libdir.'/tablelib.php');

$date        = optional_param('date', 0, PARAM_INT); // Date to display.
$params = array();
if (!empty($date)) {
    $params['date'] = $date;
}

$pageurl = new \moodle_url('/admin/tool/objectfs/object_status_history.php', $params);
$heading = get_string('object_status:historypage', 'tool_objectfs');
$PAGE->set_url($pageurl);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('report');
$PAGE->set_title($heading);
$PAGE->set_heading($heading);

admin_externalpage_setup('tool_objectfs_object_status_history');

$OUTPUT = $PAGE->get_renderer('tool_objectfs');
echo $OUTPUT->header();

echo "<style>.ofs-bar { background: #17a5eb; white-space: nowrap; }</style>";

$dates = $OUTPUT->get_date_options();
if (!empty($date) && array_key_exists($date, $dates)) {
    $reportdate = $date;
} else {
    $reportdate = key($dates);
}

echo html_writer::start_tag('form', array('class' => 'reportselecform', 'action' => $pageurl, 'method' => 'get'));
//echo html_writer::empty_tag('input', array('type' => 'submit', 'value' => 'Previous report', 'class' => 'btn btn-secondary'));
//echo html_writer::span('          ');
echo html_writer::select($dates, "date", $reportdate);
echo html_writer::span('          ');
echo html_writer::empty_tag('input', array('type' => 'submit', 'value' => 'Get this report', 'class' => 'btn btn-secondary'));
//echo html_writer::span('          ');
//echo html_writer::empty_tag('input', array('type' => 'submit', 'value' => 'Next report', 'class' => 'btn btn-secondary'));
echo html_writer::end_tag('form');

$reporttypes = tool_objectfs\local\report\objectfs_report::get_report_types();
foreach ($reporttypes as $reporttype) {
    $table = new tool_objectfs\local\report\object_status_history_table($reporttype, $reportdate);
    $table->baseurl = $pageurl;
    $table->out(100, false);
    echo "<br>";
}

echo $OUTPUT->footer();
