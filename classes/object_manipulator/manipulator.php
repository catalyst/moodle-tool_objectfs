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
 * File manipulator abstract class.
 *
 * @package   tool_objectfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\object_manipulator;

use tool_objectfs\object_manipulator\logger;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/admin/tool/objectfs/lib.php');

abstract class manipulator {

    /**
     * object file system
     *
     * @var object_file_system
     */
    protected $filesystem;

    /**
     * What time the file manipulator should finish execution by.
     *
     * @var int
     */
    protected $finishtime;

    protected $logger;

    /**
     * Manipulator constructor
     *
     * @param object_file_system $filesystem object file system
     * @param int $maxruntime What time the file manipulator should finish execution by
     */
    public function __construct($filesystem, $config) {
         $this->finishtime = time() + $config->maxtaskruntime;
         $this->filesystem = $filesystem;
    }

    /**
     * get candidate content hashes for execution.
     *
     * @return array $candidatehashes candidate content hashes
     */
    abstract public function get_candidate_objects();

    /**
     * Pushes files from local file system to remote.
     *
     * @param  array $candidatehashes content hashes to push
     */
    public function execute($objectrecords) {

        if (!$this->manipulator_can_execute()) {
            mtrace('Objectfs manipulator exiting early');
            return;
        }

        if (count($objectrecords) == 0) {
            mtrace('No candidate objects found.');
            return;
        }

        $this->logger->start_timing();

        foreach ($objectrecords as $objectrecord) {
            if (time() >= $this->finishtime) {
                break;
            }

            $objectlock = $this->filesystem->acquire_object_lock($objectrecord->contenthash);

            // Object is currently being manipulated elsewhere.
            if (!$objectlock) {
                continue;
            }

            $newlocation = $this->manipulate_object($objectrecord);

            update_object_record($objectrecord->contenthash, $newlocation);

            $objectlock->release();
        }

        $this->logger->end_timing();
        $this->logger->output_move_statistics();
    }

    protected function manipulator_can_execute() {
        return true;
    }



    public static function get_all_manipulator_classnames() {
        $manipulators = array('deleter',
                              'puller',
                              'pusher',
                              'recoverer');

        foreach ($manipulators as $key => $manipulator) {
            $manipulators[$key] = '\\tool_objectfs\\object_manipulator\\' . $manipulator;
        }

        return $manipulators;
    }

    public static function setup_and_run_object_manipulator($manipulatorclassname) {
        $config = get_objectfs_config();

        if (!tool_objectfs_should_tasks_run()) {
            mtrace(get_string('not_enabled', 'tool_objectfs'));
            return;
        }

        $filesystem = new $config->filesystem();

        if (!$filesystem->get_client_availability()) {
            mtrace(get_string('client_not_available', 'tool_objectfs'));
            return;
        }

        $logger = new \tool_objectfs\log\aggregate_logger();
        $manipulator = new $manipulatorclassname($filesystem, $config, $logger);
        $candidatehashes = $manipulator->get_candidate_objects();
        $manipulator->execute($candidatehashes);
    }
}
