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

admin_externalpage_setup('tool_objectfs_object_status');

use tool_objectfs\local\report\objectfs_report;
use tool_objectfs\local\report\object_status_history_table;

$reportid = optional_param('reportid', 0, PARAM_INT);

$baseurl = '/admin/tool/objectfs/object_status.php';
$pageurl = new \moodle_url($baseurl, ['reportid' => $reportid]);
$heading = get_string('object_status:page', 'tool_objectfs');
$PAGE->set_url($pageurl);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('report');
$PAGE->set_title($heading);
$PAGE->set_heading($heading);

$OUTPUT = $PAGE->get_renderer('tool_objectfs');
echo $OUTPUT->header();

if ($reports = objectfs_report::get_report_ids()) {
    if (empty($reportid) || !array_key_exists($reportid, $reports)) {
        $reportid = key($reports);
    }
    echo $OUTPUT->object_status_history_page_header($reports, $reportid);

    $reporttypes = objectfs_report::get_report_types();
    foreach ($reporttypes as $reporttype) {
        echo $OUTPUT->box_start();
        $table = new object_status_history_table($reporttype, $reportid);
        $table->baseurl = $pageurl;

        $heading = get_string('object_status:' . $reporttype, 'tool_objectfs');
        echo $OUTPUT->heading($heading, 2);
        $table->out(0, false);
        echo $OUTPUT->box_end();
    }
} else {
    echo $OUTPUT->heading(get_string('nothingtodisplay'));
}

echo $OUTPUT->footer();
