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
 * Missing files page.
 *
 * @package   tool_objectfs
 * @author    Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once( __DIR__ . '/lib.php');
require_once($CFG->libdir.'/adminlib.php');

use tool_objectfs\table\files_table;

$download = optional_param('download', '', PARAM_ALPHA);

admin_externalpage_setup('tool_objectfs');

$output = $PAGE->get_renderer('tool_objectfs');
$table = new files_table('missing-files', OBJECT_LOCATION_ERROR);
$table->define_baseurl('/admin/tool/objectfs/missing_files.php');

if ($table->is_downloading($download, get_string('filename:missingfiles', 'tool_objectfs'))) {
    $table->out(200, false);
    die();
}

echo $output->header();
echo $output->heading(get_string('page:missingfiles', 'tool_objectfs'));

$table->out(200, false);

echo $output->footer();
