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
 * Class candidates_factory
 * @package tool_objectfs
 * @author Gleimer Mora <gleimermora@catalyst-au.net>
 * @copyright Catalyst IT
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\local\object_manipulator\candidates;

use moodle_exception;
use stdClass;
use tool_objectfs\local\object_manipulator\checker;
use tool_objectfs\local\object_manipulator\deleter;
use tool_objectfs\local\object_manipulator\puller;
use tool_objectfs\local\object_manipulator\pusher;
use tool_objectfs\local\object_manipulator\recoverer;
use tool_objectfs\local\object_manipulator\orphaner;

/**
 * Candidates Factory
 *
 * Maps manipulator classes to candidate finders. Uses bitmask_candidates for
 * manipulators whose candidates are determined by location bitmask filters.
 */
class candidates_factory {
    /**
     * Manipulators that use the legacy class-based mapping (non-bitmask queries).
     * @var array
     */
    private static $legacymap = [
        checker::class => checker_candidates::class,
        orphaner::class => orphaner_candidates::class,
    ];

    /**
     * Get the bitmask map for manipulators.
     * Must be a method rather than a static property because the OBJECT_LOCATION_*
     * constants are defined at runtime via define().
     *
     * @return array
     */
    private static function get_bitmask_map(): array {
        return [
            pusher::class => [
                'has_mask' => OBJECT_LOCATION_IN_FILEDIR | OBJECT_LOCATION_IN_MDL_FILES, // Must be local and referenced.
                'not_mask' => OBJECT_LOCATION_IN_REMOTE, // Must not be in remote.
                'queryname' => 'get_push_candidates',
            ],
            puller::class => [
                'has_mask' => OBJECT_LOCATION_IN_MDL_FILES | OBJECT_LOCATION_IN_REMOTE, // Must be referenced and in remote.
                'not_mask' => OBJECT_LOCATION_IN_FILEDIR, // Must not be local.
                'queryname' => 'get_pull_candidates',
            ],
            deleter::class => [
                'has_mask' => OBJECT_LOCATION_IN_FILEDIR | OBJECT_LOCATION_IN_MDL_FILES | OBJECT_LOCATION_IN_REMOTE,
                'not_mask' => 0, // All bits set, nothing excluded.
                'queryname' => 'get_delete_candidates',
            ],
            recoverer::class => [
                'has_mask' => OBJECT_LOCATION_IN_MDL_FILES, // Must be referenced.
                'not_mask' => OBJECT_LOCATION_IN_FILEDIR | OBJECT_LOCATION_IN_REMOTE, // Must not be local or remote.
                'queryname' => 'get_recover_candidates',
            ],
        ];
    }

    /**
     * Create a candidate finder for the given manipulator.
     *
     * @param string $manipulator Manipulator class name.
     * @param stdClass $config Plugin config.
     * @return manipulator_candidates
     * @throws moodle_exception
     */
    public static function finder($manipulator, stdClass $config) {
        // Legacy candidates (checker, orphaner) use non-bitmask SQL.
        if (isset(self::$legacymap[$manipulator])) {
            $classname = self::$legacymap[$manipulator];
            return new $classname($config);
        }

        // Bitmask-based candidates.
        $bitmaskmap = self::get_bitmask_map();
        if (isset($bitmaskmap[$manipulator])) {
            $entry = $bitmaskmap[$manipulator];
            $options = self::get_options_for_manipulator($manipulator, $config);
            return new bitmask_candidates(
                $config,
                $entry['has_mask'],
                $entry['not_mask'],
                $entry['queryname'],
                $options
            );
        }

        throw new moodle_exception('invalidclass', 'error', '', 'Invalid manipulator class');
    }

    /**
     * Build filter options for a bitmask manipulator based on config.
     *
     * @param string $manipulator Manipulator class name.
     * @param stdClass $config Plugin config.
     * @return array
     */
    private static function get_options_for_manipulator(string $manipulator, stdClass $config): array {
        switch ($manipulator) {
            case pusher::class:
                $filesystem = new $config->filesystem();
                return [
                    'threshold' => $config->sizethreshold,
                    'max_filesize' => $filesystem->get_maximum_upload_filesize(),
                    'maxage' => time() - $config->minimumage,
                ];

            case puller::class:
                return [
                    'size_ceiling' => $config->sizethreshold,
                ];

            case deleter::class:
                return [
                    'threshold' => $config->sizethreshold,
                    'maxage' => time() - $config->consistencydelay,
                ];

            case recoverer::class:
            default:
                return [];
        }
    }
}
