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
 * File status page - stats on where files are b/w local file system and s3
 *
 * @package   tool_objectfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_objectfs\form\settings_form;

require_once(__DIR__ . '/../../../config.php');
require_once( __DIR__ . '/lib.php');
require_once($CFG->libdir.'/adminlib.php');

admin_externalpage_setup('tool_objectfs_settings');

$output = $PAGE->get_renderer('tool_objectfs');

$config = get_objectfs_config();

$config->sizethreshold /= 1024; // Convert to KB.

$form = new settings_form(null, array('config' => $config));

if ($data = $form->get_data()) {
    $data->sizethreshold *= 1024; // Convert back to Bytes.
    set_objectfs_config($data);
    redirect(new moodle_url('/admin/tool/objectfs/index.php'));
}

echo $output->header();
echo $output->heading(get_string('pluginname', 'tool_objectfs'));
$form->display();
echo $output->footer();



