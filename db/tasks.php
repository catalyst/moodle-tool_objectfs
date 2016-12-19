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
 * tool_sssfs tasks
 *
 * @package   tool_sssfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = array(
    array(
        'classname' => 'tool_sssfs\task\push_to_sss',
        'blocking'  => 0,
        'minute'    => '*',
        'hour '     => '*',
        'day'       => '*',
        'dayofweek' => '*',
        'month'     => '*'
    ),
    array(
        'classname' => 'tool_sssfs\task\generate_status_report',
        'blocking'  => 0,
        'minute'    => '7',
        'hour '     => '*',
        'day'       => '*',
        'dayofweek' => '*',
        'month'     => '*'
    ),
    array(
        'classname' => 'tool_sssfs\task\clean_up_files',
        'blocking'  => 0,
        'minute'    => '7',
        'hour '     => '*',
        'day'       => '*',
        'dayofweek' => '*',
        'month'     => '*'
    ),
    array(
        'classname' => 'tool_sssfs\task\pull_from_sss',
        'blocking'  => 0,
        'minute'    => '*',
        'hour '     => '*',
        'day'       => '*',
        'dayofweek' => '*',
        'month'     => '*'
    ),
);

