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

function xmldb_tool_objectfs_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2017030300) {

        $table = new xmldb_table('tool_objectfs_report_data');
        $dbman->rename_table($table, 'tool_objectfs_reports');

        $table = new xmldb_table('tool_objectfs_reports');

        // Changing type of field reporttype on table tool_objectfs_reports to char.
        $field = new xmldb_field('reporttype', XMLDB_TYPE_CHAR, '15', null, XMLDB_NOTNULL, null, null, 'id');

        // Launch change of type for field reporttype.
        $dbman->change_field_type($table, $field);

        upgrade_plugin_savepoint(true, 2017030300, 'tool', 'objectfs');
    }

    if ($oldversion < 2017031000) {
        $table = new xmldb_table('tool_objectfs_objects');
        $key = new xmldb_key('contenthash', XMLDB_KEY_UNIQUE, array('contenthash'));
        $dbman->add_key($table, $key);

        upgrade_plugin_savepoint(true, 2017031000, 'tool', 'objectfs');
    }

    if ($oldversion < 2017111700) {
        $config = get_config('tool_objectfs');

        // Remapping the new namespaced variables.
        if (!empty($config->key)) {
            set_config('s3_key', $config->key, 'tool_objectfs');
        }

        if (!empty($config->secret)) {
            set_config('s3_secret', $config->secret, 'tool_objectfs');
        }

        if (!empty($config->bucket)) {
            set_config('s3_bucket', $config->bucket, 'tool_objectfs');
        }

        if (!empty($config->region)) {
            set_config('s3_region', $config->region, 'tool_objectfs');
        }

        // Use the existing filesystem that was once hardcoded.
        set_config('filesystem', '\\tool_objectfs\\s3_file_system', 'tool_objectfs');

        // Adding default variables for the azure config.
        set_config('azure_accountname', '', 'tool_objectfs');
        set_config('azure_container', '', 'tool_objectfs');
        set_config('azure_sastoken', '', 'tool_objectfs');

        // Cleaning up previous variables.
        unset_config('key', 'tool_objectfs');
        unset_config('secret', 'tool_objectfs');
        unset_config('bucket', 'tool_objectfs');
        unset_config('region', 'tool_objectfs');

        upgrade_plugin_savepoint(true, 2017111700, 'tool', 'objectfs');
    }

    return true;
}
