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

require_once(__DIR__ . '/classes/local/manager.php');

global $PAGE, $CFG;

if (!$hassiteconfig) {
    return;
}

$settings = new admin_settingpage('tool_objectfs', get_string('pluginname', 'tool_objectfs'));
$ADMIN->add('tools', $settings);

$ADMIN->add('tools', new admin_externalpage('tool_objectfs_presignedurl_testing',
    get_string('presignedurl_testing:page', 'tool_objectfs'),
    new moodle_url('/admin/tool/objectfs/presignedurl_tests.php')));

$ADMIN->add('reports', new admin_externalpage('tool_objectfs_object_status',
    get_string('object_status:page', 'tool_objectfs'),
    new moodle_url('/admin/tool/objectfs/object_status.php')));

$ADMIN->add('tools', new admin_externalpage('tool_objectfs_missing_files',
    get_string('page:missingfiles', 'tool_objectfs'),
    new moodle_url('/admin/tool/objectfs/missing_files.php')));

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading('tool_objectfs/generalsettings',
        new lang_string('settings:generalheader', 'tool_objectfs'), ''));

    $settings->add(new admin_setting_configcheckbox('tool_objectfs/enabletasks',
        new lang_string('settings:enabletasks', 'tool_objectfs'), '', ''));

    $settings->add(new admin_setting_configduration('tool_objectfs/maxtaskruntime',
        new lang_string('settings:maxtaskruntime', 'tool_objectfs'), '', '', MINSECS));

    $settings->add(new admin_setting_configcheckbox('tool_objectfs/enablelogging',
        new lang_string('settings:enablelogging', 'tool_objectfs'), '', ''));


    $settings->add(new admin_setting_heading('tool_objectfs/filetransfersettings',
        new lang_string('settings:filetransferheader', 'tool_objectfs'), ''));

    $settings->add(new admin_setting_configtext('tool_objectfs/sizethreshold',
        new lang_string('settings:sizethreshold', 'tool_objectfs'), '', 1024 * 10, PARAM_INT));

    $settings->add(new admin_setting_configtext('tool_objectfs/batchsize',
        new lang_string('settings:batchsize', 'tool_objectfs'), '', 10000, PARAM_INT));

    $settings->add(new admin_setting_configduration('tool_objectfs/minimumage',
        new lang_string('settings:minimumage', 'tool_objectfs'), '', '', 7 * DAYSECS));

    $settings->add(new admin_setting_configcheckbox('tool_objectfs/deletelocal',
        new lang_string('settings:deletelocal', 'tool_objectfs'), '', ''));

    $settings->add(new admin_setting_configduration('tool_objectfs/consistencydelay',
        new lang_string('settings:consistencydelay', 'tool_objectfs'), '', '', 10 * MINSECS));


    $settings->add(new admin_setting_heading('tool_objectfs/storagefilesystemselection',
        new lang_string('settings:clientselection:header', 'tool_objectfs'), ''));

    $settings->add(new admin_setting_configselect('tool_objectfs/filesystem',
        new lang_string('settings:clientselection:title', 'tool_objectfs'),
        new lang_string('settings:clientselection:title_help', 'tool_objectfs'), '',
        \tool_objectfs\local\manager::get_fs_list()));


    $config = \tool_objectfs\local\manager::get_objectfs_config();
    $support = false;
    if (!empty($config->filesystem)) {
        $support = (new $config->filesystem())->supports_presigned_urls();
    }
    $warning = !method_exists('file_system', 'supports_xsendfile');
    $warningtext = $warning ? $OUTPUT->notification(get_string('settings:presignedurl:coresupport', 'tool_objectfs')) : '';
    $warningtext .= \tool_objectfs\local\manager::cloudfront_pem_exists();
    if ($support) {
        $settings->add(new admin_setting_heading('tool_objectfs/presignedurls',
            new lang_string('settings:presignedurl:header', 'tool_objectfs'), $warningtext));

        $settings->add(new admin_setting_configcheckbox('tool_objectfs/enablepresignedurls',
            new lang_string('settings:presignedurl:enablepresignedurls', 'tool_objectfs'),
            new lang_string('settings:presignedurl:enablepresignedurls_help', 'tool_objectfs'), ''));

        $settings->add(new admin_setting_configduration('tool_objectfs/expirationtime',
            new lang_string('settings:presignedurl:expirationtime', 'tool_objectfs'),
            new lang_string('settings:presignedurl:expirationtime_help', 'tool_objectfs'), 2 * HOURSECS, HOURSECS));

        $settings->add(new admin_setting_configtext('tool_objectfs/presignedminfilesize',
            new lang_string('settings:presignedurl:presignedminfilesize', 'tool_objectfs'),
            new lang_string('settings:presignedurl:presignedminfilesize_help', 'tool_objectfs'), 0, PARAM_INT));

        $settings->add(
            new admin_setting_filetypes(
                'tool_objectfs/signingwhitelist',
                new lang_string('settings:presignedurl:whitelist', OBJECTFS_PLUGIN_NAME),
                new lang_string('settings:presignedurl:whitelist_help', OBJECTFS_PLUGIN_NAME)
            )
        );

        $settings->add(
            new admin_setting_configselect(
                'tool_objectfs/signingmethod',
                get_string('settings:presignedurl:enablepresignedurlschoice', OBJECTFS_PLUGIN_NAME),
                '',
                's3',
                ['s3' => 'S3', 'cf' => 'CloudFront']
            )
        );

        if ('cf' === $config->signingmethod) {
            $settings->add(
                new admin_setting_configtext('tool_objectfs/cloudfrontresourcedomain',
                    get_string('settings:presignedcloudfronturl:cloudfront_resource_domain', OBJECTFS_PLUGIN_NAME),
                    get_string('settings:presignedcloudfronturl:cloudfront_resource_domain_help', OBJECTFS_PLUGIN_NAME),
                    '',
                    PARAM_TEXT
                )
            );

            $settings->add(
                new admin_setting_configtext('tool_objectfs/cloudfrontkeypairid',
                    get_string('settings:presignedcloudfronturl:cloudfront_key_pair_id', OBJECTFS_PLUGIN_NAME),
                    get_string('settings:presignedcloudfronturl:cloudfront_key_pair_id_help', OBJECTFS_PLUGIN_NAME),
                    '',
                    PARAM_TEXT
                )
            );

            $settings->add(
                new admin_setting_configtextarea('tool_objectfs/cloudfrontprivatekey',
                    get_string('settings:presignedcloudfronturl:cloudfront_private_key_pem', OBJECTFS_PLUGIN_NAME),
                    get_string('settings:presignedcloudfronturl:cloudfront_private_key_pem_help', OBJECTFS_PLUGIN_NAME),
                    '',
                    PARAM_TEXT
                )
            );
        }
    }

    $client = \tool_objectfs\local\manager::get_client($config);
    if ($client && $client->get_availability()) {
        $settings = $client->define_client_section($settings, $config);

        if ($PAGE->has_set_url() && $PAGE->url->compare(new moodle_url('/admin/settings.php?section=tool_objectfs'))) {
            $client->define_client_check($client);
        }
    }

    $settings->add(new admin_setting_heading('tool_objectfs/testsettings',
        new lang_string('settings:testingheader', 'tool_objectfs'), ''));

    $settings->add(new admin_setting_configcheckbox('tool_objectfs/preferexternal',
        new lang_string('settings:preferexternal', 'tool_objectfs'), '', ''));
}
