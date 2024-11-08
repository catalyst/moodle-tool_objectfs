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

namespace tool_objectfs;

use tool_objectfs\local\store\azure_blob_storage\file_system;

/**
 * File system for Azure Blob Storage.
 *
 * This file tells objectfs that this storage system is available for use.
 * E.g. via $CFG->alternative_file_system_class
 *
 * @package    tool_objectfs
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class azure_blob_storage_file_system extends file_system {
}
