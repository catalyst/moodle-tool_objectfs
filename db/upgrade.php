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

        upgrade_plugin_savepoint(true, 2017030300, 'error', 'objectfs');
    }

    return true;
}