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
 * Upgrade script for the Objectfs plugin.
 *
 * @package   tool_objectfs
 * @author    Mikhail Golenkov <mikhailgolenkov@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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
        unset_config('key_prefix', 'tool_objectfs');

        upgrade_plugin_savepoint(true, 2017111700, 'tool', 'objectfs');
    }

    if ($oldversion < 2020030900) {
        $table = new xmldb_table('tool_objectfs_objects');
        $index = new xmldb_index('toolobjeobje_con_idu_ix', XMLDB_INDEX_UNIQUE, ['contenthash, location']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2020030900, 'tool', 'objectfs');
    }

    if ($oldversion < 2020052600) {
        $dbman->drop_table(new xmldb_table('tool_objectfs_reports'));

        $table = new xmldb_table('tool_objectfs_reports');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('reportdate', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_index('reportdate_idx', XMLDB_INDEX_NOTUNIQUE, ['reportdate']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('tool_objectfs_report_data');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('reportid', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL);
        $table->add_field('reporttype', XMLDB_TYPE_CHAR, 15, null, XMLDB_NOTNULL);
        $table->add_field('datakey', XMLDB_TYPE_CHAR, 15, null, XMLDB_NOTNULL);
        $table->add_field('objectcount', XMLDB_TYPE_INTEGER, 15, null, XMLDB_NOTNULL);
        $table->add_field('objectsum', XMLDB_TYPE_INTEGER, 20, null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_index('reporttype_idx', XMLDB_INDEX_NOTUNIQUE, ['reporttype']);
        $table->add_index('reportid_idx', XMLDB_INDEX_NOTUNIQUE, ['reportid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2020052600, 'tool', 'objectfs');
    }

    if ($oldversion < 2021090100) {
        // If set already, make sure we use the same default value.
        if (isset($CFG->tool_objectfs_delete_externally)) {
            set_config('deleteexternal', $CFG->tool_objectfs_delete_externally, 'tool_objectfs');
        }

        upgrade_plugin_savepoint(true, 2021090100, 'tool', 'objectfs');
    }

    if ($oldversion < 2022070401) {

        // Add filesize field to objects table.
        $table = new xmldb_table('tool_objectfs_objects');
        $field = new xmldb_field('filesize', XMLDB_TYPE_INTEGER, '20');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        } else {
            $dbman->drop_field($table, $field);
            $dbman->add_field($table, $field);
        }

        // Populate the filesize field.
        \core\task\manager::queue_adhoc_task(new \tool_objectfs\task\populate_objects_filesize());

        upgrade_plugin_savepoint(true, 2022070401, 'tool', 'objectfs');
    }

    if ($oldversion < 2023013100) {
        // Check to make sure adhoc task not already running.
        if (!$DB->record_exists('task_adhoc', ['classname' => '\tool_objectfs\task\populate_objects_filesize'])) {
            // Populate the filesize field.
            \core\task\manager::queue_adhoc_task(new \tool_objectfs\task\populate_objects_filesize());
        }

        upgrade_plugin_savepoint(true, 2023013100, 'tool', 'objectfs');
    }
    return true;
}
