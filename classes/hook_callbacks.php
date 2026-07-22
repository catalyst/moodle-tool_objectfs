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

use tool_objectfs\local\store\object_file_system;

/**
 * Hook callbacks for tool_objectfs.
 *
 * @package   tool_objectfs
 * @author    Benjamin Walker (benjaminwalker@catalyst-au.net)
 * @copyright 2026 Catalyst IT Australia
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Rewrites embedded pre-signed URLs back to pluginfiles before editor content is persisted
     *
     * @param \core_files\hook\before_editor_content_saved $hook
     */
    public static function before_editor_content_saved(\core_files\hook\before_editor_content_saved $hook): void {
        global $CFG;

        if (during_initial_install() || isset($CFG->upgraderunning)) {
            return;
        }

        $fs = get_file_storage()->get_file_system();
        if (!($fs instanceof object_file_system)) {
            return;
        }

        $hook->set_text($fs->normalise_presigned_urls($hook->get_text()));
    }
}
