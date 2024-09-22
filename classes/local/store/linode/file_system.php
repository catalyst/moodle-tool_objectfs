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
 * object_file_system abstract class.
 *
 * Remote object storage providers extent this class.
 * At minimum you need to implement get_remote_client.
 *
 * @package   tool_objectfs
 * @author    Brian Yanosik <kisonay@gmail.com>
 * @copyright Brian Yanosik
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\local\store\linode;

defined('MOODLE_INTERNAL') || die();

use tool_objectfs\local\store\s3\file_system as s3_file_system;

require_once($CFG->dirroot . '/admin/tool/objectfs/lib.php');

/**
 * [Description file_system]
 */
class file_system extends s3_file_system {

    /**
     * initialise_external_client
     * @param \stdClass $config
     *
     * @return client
     */
    protected function initialise_external_client($config) {
        $linodeclient = new client($config);

        return $linodeclient;
    }

}
