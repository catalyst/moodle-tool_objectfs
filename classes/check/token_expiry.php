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

namespace tool_objectfs\check;

use core\check\check;
use core\check\result;

/**
 * Token expiry check.
 *
 * @package    tool_objectfs
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class token_expiry extends check {
    /**
     * Checks the token expiry time against thresholds
     * @return result
     */
    public function get_result(): result {
        $config = \tool_objectfs\local\manager::get_objectfs_config();
        $client = \tool_objectfs\local\manager::get_client($config);

        // No client set - n/a.
        if (empty($client)) {
            return new result(result::NA, get_string('check:tokenexpiry:na', 'tool_objectfs'));
        }

        $expirytime = $client->get_token_expiry_time();
        $secondsleft = $expirytime - time();

        $strparams = [
            'dayssince' => abs(round($secondsleft / DAYSECS)),
            'time' => userdate($expirytime),
        ];

        // Not implemented or token not set - n/a.
        if ($expirytime == -1) {
            return new result(result::NA, get_string('check:tokenexpiry:na', 'tool_objectfs'));
        }

        // Is in past - token has expired.
        if ($secondsleft < 0) {
            return new result(result::CRITICAL, get_string('check:tokenexpiry:expired', 'tool_objectfs', $strparams));
        }

        // Is in warning period - warn.
        $warnthreshold = (int) $config->tokenexpirywarnperiod;
        if ($secondsleft < $warnthreshold) {
            return new result(result::WARNING, get_string('check:tokenexpiry:expiresin', 'tool_objectfs', $strparams));
        }

        // Else ok.
        return new result(result::OK, get_string('check:tokenexpiry:expiresin', 'tool_objectfs', $strparams));
    }
}
