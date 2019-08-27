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

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir.'/adminlib.php');

admin_externalpage_setup('tool_objectfs_presignedurl_testing');

$output = $PAGE->get_renderer('tool_objectfs');

echo $output->header();
echo $output->heading(get_string('presignedurl_testing:page', 'tool_objectfs'));

$config = get_objectfs_config();
$support = tool_objectfs_filesystem_supports_presigned_urls($config->filesystem);
$settingslink = \html_writer::link(new \moodle_url('/admin/tool/objectfs/index.php'), get_string('presignedurl_testing:objectfssettings', 'tool_objectfs'));

if ($support) {

    $client = tool_objectfs_get_client($config);
    if ($client and $client->get_availability()) {

        $connection = $client->test_connection();
        if ($connection->success) {
            $fs = new $config->filesystem();
            $testfiles = $output->presignedurl_tests_load_files($fs);
            echo $output->presignedurl_tests_content($fs, $testfiles);
        } else {
            echo $output->notification($connection->message, 'notifyproblem');
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
