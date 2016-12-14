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

use tool_sssfs\sss_client;

defined('MOODLE_INTERNAL') || die;


class sss_mock_client extends sss_client {

    private $pushsuccess;
    private $checksuccess;

    public function __construct() {
        $this->pushsuccess = true;
        $this->checksuccess = true;
    }

    // True for success, false for failure.
    public function set_push_success($success) {
        $this->pushsuccess = $success;
    }

    // True for success, false for failure.
    public function set_check_success($success) {
        $this->checksuccess = $success;
    }

    public function push_file($filekey, $filecontent) {
        if ($this->pushsuccess) {
            return true;
        } else {
            return false; // Mock a failure.
        }
    }

    public function check_file($filekey, $expectedsize) {
        if ($this->checksuccess) {
            return true;
        } else {
            return false; // Mock a failure.
        }
    }

}

