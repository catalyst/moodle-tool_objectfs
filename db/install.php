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

defined('MOODLE_INTERNAL') || die;

function xmldb_tool_objectfs_install() {
    global $CFG;

    // We do this so when backporting the lock API for < mdl26 we dont have to mess with core upgrade.php and version
    // numbers, so we conditionally install the table for earlier versions of moodle. It will be removed with the
    // plugin if no xml files define the table.
    if ($CFG->branch <= 26) {
        global $DB;
        $dbman = $DB->get_manager();
        // Define table lock_db to be created.
        $table = new xmldb_table('lock_db');

        // Adding fields to table lock_db.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('resourcekey', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('expires', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('owner', XMLDB_TYPE_CHAR, '36', null, null, null, null);

        // Adding keys to table lock_db.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table lock_db.
        $table->add_index('resourcekey_uniq', XMLDB_INDEX_UNIQUE, array('resourcekey'));
        $table->add_index('expires_idx', XMLDB_INDEX_NOTUNIQUE, array('expires'));
        $table->add_index('owner_idx', XMLDB_INDEX_NOTUNIQUE, array('owner'));

        // Conditionally launch create table for lock_db.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
    }

    return true;
}
