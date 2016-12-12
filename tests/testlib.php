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
 * local_catdeleter scheduler tests.
 *
 * @package   local_catdeleter
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die;

function save_file_to_local_storage($filesize = 10, $filename = 'test.txt', $filecontent = 'test') {
    global $DB;
    $fs = get_file_storage();

    $syscontext = context_system::instance();
    $component = 'core';
    $filearea  = 'unittest';
    $itemid    = 0;
    $filepath  = '/';
    $sourcefield = 'Copyright stuff';

    $filerecord = array(
        'contextid' => $syscontext->id,
        'component' => $component,
        'filearea'  => $filearea,
        'itemid'    => $itemid,
        'filepath'  => $filepath,
        'filename'  => $filename,
        'source'    => $sourcefield,
    );
    $file = $fs->create_file_from_string($filerecord, $filecontent);

    // Above method does not set a file size, we do this so we do and have control over it.
    $DB->set_field('files', 'filesize', $filesize, array('contenthash' => $file->get_contenthash()));

    return $file;
}

function generate_config($sizethreshold = 0, $minimumage = -10, $consistancydelay = -1) {
    $config = new stdClass();
    $config->enabled = 1;
    $config->key = 123;
    $config->secret = 123;
    $config->bucket = 'test-bucket';
    $config->region = 'aws-region';
    $config->sizethreshold = $sizethreshold * 1024; // Convert from kb.
    $config->minimumage = $minimumage;
    $config->consistancydelay = $consistancydelay;
    $config->logginglocation = '';

    return $config;
}

