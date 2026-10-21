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
 * Reset and requeue the objectfs location bits migration.
 *
 * This keeps the legacy location column and recreates the migrated columns so
 * the migration task can populate them again from the legacy source.
 *
 * @package    tool_objectfs
 * @subpackage cli
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params(
    [
        'help' => false,
        'reset' => false,
        'queuetask' => false,
    ],
    [
        'h' => 'help',
        'r' => 'reset',
        'q' => 'queuetask',
    ]
);

if ($unrecognized) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognized)));
}

$help = <<<EOT
Reset and requeue the objectfs location bits migration.

Without --reset, this command only reports what would be reset.

Options:
 -h, --help     Print out this help
 -r, --reset    Reset the bit columns and migration progress, then queue the migration task
 -q, --queuetask    Queue the migration task after resetting the bit columns

Example:
 \$sudo -u www-data /usr/bin/php admin/tool/objectfs/cli/objectfs_reset_location_bits.php --reset --queuetask

EOT;

if ($options['help']) {
    echo $help;
    exit(0);
}

if (CLI_MAINTENANCE) {
    cli_error('CLI maintenance mode active, migration reset suspended.');
}

if (moodle_needs_upgrading()) {
    cli_error('Moodle upgrade pending, migration reset suspended.');
}

global $DB;

cli_writeln('Counting objectfs objects.');
$objectcount = $DB->count_records('tool_objectfs_objects');
cli_writeln("Objectfs objects: {$objectcount}");
cli_writeln('Migration complete flag: ' . ((bool) get_config('tool_objectfs', 'locationbitsmigrationcomplete') ? '1' : '0'));

if (!$options['reset']) {
    cli_writeln('Nothing changed. Re-run with --reset to reset and requeue the migration.');
    exit(0);
}

$dbman = $DB->get_manager();
$table = new xmldb_table('tool_objectfs_objects');
$index = new xmldb_index('ix_location_bits', XMLDB_INDEX_NOTUNIQUE, ['in_filedir', 'in_mdl_files', 'in_remote', 'filesize']);

if ($dbman->index_exists($table, $index)) {
    $dbman->drop_index($table, $index);
    cli_writeln('[db] Dropped ix_location_bits.');
} else {
    cli_writeln('[db] ix_location_bits does not exist.');
}

foreach (['in_filedir', 'in_mdl_files', 'in_remote'] as $name) {
    $field = new xmldb_field($name);
    if ($dbman->field_exists($table, $field)) {
        $dbman->drop_field($table, $field);
        cli_writeln("[db] Dropped {$name}.");
    } else {
        cli_writeln("[db] {$name} does not exist.");
    }
}

foreach (
    [
    ['in_filedir', 'tagslastpushed'],
    ['in_mdl_files', 'in_filedir'],
    ['in_remote', 'in_mdl_files'],
    ] as [$name, $after]
) {
    $field = new xmldb_field($name, XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', $after);
    $dbman->add_field($table, $field);
    cli_writeln("[db] Added {$name}.");
}

$dbman->add_index($table, $index);
cli_writeln('[db] Added ix_location_bits.');

set_config('locationbitsmigrationcomplete', 0, 'tool_objectfs');
set_config('locationbitsmigrationlastid', 0, 'tool_objectfs');
set_config('locationbitsmigrationtotal', $objectcount, 'tool_objectfs');
set_config('locationbitsmigrationdonesofar', 0, 'tool_objectfs');
cli_writeln('Migration configuration reset.');

if ($options['queuetask']) {
    \core\task\manager::reschedule_or_queue_adhoc_task(new \tool_objectfs\task\migrate_object_locations());
    cli_writeln('Location bits reset and migration task queued.');
} else {
    cli_writeln('Location bits reset, migration task not queued.');
}
