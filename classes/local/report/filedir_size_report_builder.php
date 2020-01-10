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
 * Object filedir size report builder.
 *
 * @package   tool_objectfs
 * @author    Gleimer Mora <gleimermora@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\local\report;

use tool_objectfs\local\store\object_file_system;

defined('MOODLE_INTERNAL') || die();

class filedir_size_report_builder extends objectfs_report_builder {

    /**
     * @return objectfs_report
     */
    public function build_report() {
        $report = new objectfs_report('filedir_size');
        $config = get_objectfs_config();
        /** @var object_file_system $filesystem */
        $filesystem = new $config->filesystem();

        $report->add_row('total', $filesystem->get_filedir_count(), $filesystem->get_filedir_size());
        return $report;
    }
}
