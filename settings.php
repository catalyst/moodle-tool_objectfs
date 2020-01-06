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
 * Settings
 *
 * @package   tool_objectfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/lib.php');

use core_admin\local\settings\filesize;

global $PAGE, $CFG;

if (!$ADMIN->locate('tool_objectfs')) {

    $ADMIN->add('tools', new admin_externalpage('tool_objectfs_presignedurl_testing',
        get_string('presignedurl_testing:page', 'tool_objectfs'),
        new moodle_url('/admin/tool/objectfs/presignedurl_tests.php')));

    $ADMIN->add('tools', new admin_externalpage('tool_objectfs_object_status',
        get_string('object_status:page', 'tool_objectfs'),
        new moodle_url('/admin/tool/objectfs/object_status.php')));

    $ADMIN->add('tools', new admin_externalpage('tool_objectfs_missing_files',
        get_string('page:missingfiles', 'tool_objectfs'),
        new moodle_url('/admin/tool/objectfs/missing_files.php')));
}

if ($ADMIN->fulltree) {

    $settings = new admin_settingpage('tool_objectfs', get_string('pluginname', 'tool_objectfs'));
    $ADMIN->add('tools', $settings);

    $settings->add(new \admin_setting_heading('tool_objectfs/generalsettings',
        new lang_string('settings:generalheader', 'tool_objectfs'), ''));

    $settings->add(new admin_setting_configcheckbox('tool_objectfs/enabletasks',
        new lang_string('settings:enabletasks', 'tool_objectfs'), '', '', '0', '1'));

    $settings->add(new admin_setting_configduration('tool_objectfs/maxtaskruntime',
        new lang_string('settings:maxtaskruntime', 'tool_objectfs'), '', '', MINSECS));

    $settings->add(new admin_setting_configcheckbox('tool_objectfs/enablelogging',
        new lang_string('settings:enablelogging', 'tool_objectfs'), '', '', '0', '1'));


    $settings->add(new \admin_setting_heading('tool_objectfs/filetransfersettings',
        new lang_string('settings:filetransferheader', 'tool_objectfs'), ''));

    $settings->add(new filesize('tool_objectfs/sizethreshold',
        new lang_string('settings:sizethreshold', 'tool_objectfs'), '', 1024 * filesize::UNIT_KB));

    $settings->add(new admin_setting_configtext('tool_objectfs/batchsize',
        new lang_string('settings:batchsize', 'tool_objectfs'), '', 10000, PARAM_INT));

    $settings->add(new admin_setting_configduration('tool_objectfs/minimumage',
        new lang_string('settings:minimumage', 'tool_objectfs'), '', '', 7 * DAYSECS));

    $settings->add(new admin_setting_configcheckbox('tool_objectfs/deletelocal',
        new lang_string('settings:deletelocal', 'tool_objectfs'), '', '', '0', '1'));

    $settings->add(new admin_setting_configduration('tool_objectfs/consistencydelay',
        new lang_string('settings:consistencydelay', 'tool_objectfs'), '', '', 10 * MINSECS));


    $settings->add(new admin_setting_heading('tool_objectfs/storagefilesystemselection',
        new lang_string('settings:clientselection:header', 'tool_objectfs'), ''));

    $settings->add(new admin_setting_configselect('tool_objectfs/filesystem',
        new lang_string('settings:clientselection:title', 'tool_objectfs'),
        new lang_string('settings:clientselection:title_help', 'tool_objectfs'), '',
        tool_objectfs_get_fs_list()));


    $config = get_objectfs_config();

    if (tool_objectfs_filesystem_supports_presigned_urls($config->filesystem)) {
        $settings->add(new admin_setting_heading('tool_objectfs/presignedurls',
            new lang_string('settings:presignedurl:header', 'tool_objectfs'), ''));

        $settings->add(new admin_setting_configcheckbox('tool_objectfs/enablepresignedurls',
            new lang_string('settings:presignedurl:enablepresignedurls', 'tool_objectfs'),
            new lang_string('settings:presignedurl:enablepresignedurls_help', 'tool_objectfs'), '', '0', '1'));

        $settings->add(new admin_setting_configduration('tool_objectfs/expirationtime',
            new lang_string('settings:presignedurl:expirationtime', 'tool_objectfs'),
            new lang_string('settings:presignedurl:expirationtime_help', 'tool_objectfs'), '', 10 * MINSECS));

        $settings->add(new filesize('tool_objectfs/presignedminfilesize',
            new lang_string('settings:presignedurl:presignedminfilesize', 'tool_objectfs'),
            new lang_string('settings:presignedurl:presignedminfilesize_help', 'tool_objectfs'), 0));
    }

    $client = tool_objectfs_get_client($config);
    if ($client and $client->get_availability() and $PAGE->url->compare(new moodle_url('/admin/settings.php?section=tool_objectfs'))) {
        $settings = $client->define_client_section($settings, $config);
    }

    $settings->add(new admin_setting_heading('tool_objectfs/testsettings',
        new lang_string('settings:testingheader', 'tool_objectfs'), ''));

    $settings->add(new admin_setting_configcheckbox('tool_objectfs/preferexternal',
        new lang_string('settings:preferexternal', 'tool_objectfs'), '', '', '0', '1'));
}