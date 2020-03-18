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

namespace tool_objectfs\local\object_manipulator;

use stdClass;
use tool_objectfs\local\store\object_file_system;
use tool_objectfs\log\aggregate_logger;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/admin/tool/objectfs/lib.php');

abstract class manipulator implements object_manipulator {

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

    /** @var aggregate_logger $logger */
    protected $logger;

    /** @var int $batchsize */
    protected $batchsize;

    /**
     * Size threshold for pulling files from remote in bytes.
     *
     * @var int
     */
    protected $sizethreshold;

    /**
     * Manipulator constructor
     *
     * @param object_file_system $filesystem object file system
     * @param stdClass $config
     * @param aggregate_logger $logger
     */
    public function __construct(object_file_system $filesystem, stdClass $config, aggregate_logger $logger) {
        $this->finishtime = time() + $config->maxtaskruntime;
        $this->filesystem = $filesystem;
        $this->batchsize = $config->batchsize;
        $this->sizethreshold = $config->sizethreshold;
        $this->logger = $logger;
        // Inject our logger into the filesystem.
        $this->filesystem->set_logger($this->logger);
    }

    /**
     * manipulate objects
     * @param  array $objectrecords
     */
    public function execute(array $objectrecords) {
        if (!$this->manipulator_can_execute()) {
            mtrace('Objectfs manipulator exiting early');
            return;
        }

        if (count($objectrecords) === 0) {
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

    /**
     * @param stdClass $objectrecord
     * @return int
     */
    abstract public function manipulate_object(stdClass $objectrecord);

    /**
     * @return bool
     */
    protected function manipulator_can_execute() {
        return true;
    }
}
