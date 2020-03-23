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
 * Objectfs client factory class.
 *
 * @package   tool_objectfs
 * @author    Gleimer Mora <gleimermora@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\local\store;

use tool_objectfs\config\config;
use tool_objectfs\local\store\azure\client as azure_client;
use tool_objectfs\local\store\s3\client as s3_client;
use tool_objectfs\local\store\swift\client as swift_client;

defined('MOODLE_INTERNAL') || die();

class client_factory {

    private static $filesystemtoclientmap = [
        '\tool_objectfs\azure_file_system' => azure_client::class,
        '\tool_objectfs\digitalocean_file_system' => s3_client::class,
        '\tool_objectfs\s3_file_system' => s3_client::class,
        '\tool_objectfs\swift_file_system' => swift_client::class,
    ];

    /**
     * @return object_client|bool
     */
    static public function get() {
        $config = config::instance();
        if (isset(self::$filesystemtoclientmap[$config->get('filesystem')])) {
            $classname = self::$filesystemtoclientmap[$config->get('filesystem')];
            return new $classname($config);
        }
        return false;
    }
}
