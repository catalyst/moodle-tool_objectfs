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
 * Pre-Signed URL testing page
 *
 * @package   tool_objectfs
 * @author    Mikhail Golenkov <mikhailgolenkov@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_objectfs\local\manager;

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->dirroot . '/lib/adminlib.php');

admin_externalpage_setup('tool_objectfs_presignedurl_testing');

$output = $PAGE->get_renderer('tool_objectfs');

$delete = optional_param('delete', 0, PARAM_BOOL);
$deletedsuccess = '';
if ($delete) {
    require_sesskey();
    $output->delete_presignedurl_tests_files();
    $deletedstring = get_string('settings:presignedurl:deletedsuccess', OBJECTFS_PLUGIN_NAME);
    $deletedsuccess = $output->notification($deletedstring, 'success');
}

echo $output->header();
echo $output->heading(get_string('presignedurl_testing:page', 'tool_objectfs'));
$settingslink = \html_writer::link(new \moodle_url('/admin/settings.php?section=tool_objectfs'),
    get_string('presignedurl_testing:objectfssettings', 'tool_objectfs'));

$config = manager::get_objectfs_config();
$support = false;
if (!empty($config->filesystem)) {
    $fs = new $config->filesystem();
    $support = $fs->supports_presigned_urls();
}
if ($support) {
    $deleteurl = new \moodle_url('/admin/tool/objectfs/presignedurl_tests.php', ['delete' => 1, 'sesskey' => sesskey()]);
    $deletelinktext = get_string('settings:presignedurl:deletefiles', OBJECTFS_PLUGIN_NAME);
    echo $output->heading(html_writer::link($deleteurl, $deletelinktext) . $deletedsuccess, 6);
    $client = manager::get_client($config);
    if ($client and $client->get_availability()) {
        $connection = $client->test_connection();
        if ($connection->success) {
            $testfiles = $output->presignedurl_tests_load_files($fs);
            echo $output->presignedurl_tests_content($fs, $testfiles);
        } else {
            echo $output->notification(get_string('settings:connectionfailure', 'tool_objectfs') . $connection->details, 'notifyproblem');
            echo $output->heading(get_string('presignedurl_testing:checkconnectionsettings', 'tool_objectfs').$settingslink, 5);
        }

    } else {
        echo $output->notification(get_string('settings:clientnotavailable', 'tool_objectfs'), 'notifyproblem');
        echo $output->heading(get_string('presignedurl_testing:checkclientsettings', 'tool_objectfs').$settingslink, 5);
    }

} else {
    echo $output->notification(get_string('presignedurl_testing:presignedurlsnotsupported', 'tool_objectfs'), 'notifyproblem');
    echo $output->heading(get_string('presignedurl_testing:checkfssettings', 'tool_objectfs').$settingslink, 5);
}

echo $output->footer();
