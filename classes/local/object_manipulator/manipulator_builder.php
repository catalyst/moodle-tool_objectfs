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
 * @author    Gleimer Mora <gleimermora@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\local\object_manipulator;

use coding_exception;
use moodle_exception;
use stdClass;
use tool_objectfs\local\object_manipulator\candidates\candidates_finder;
use tool_objectfs\log\aggregate_logger;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../../lib.php');

class manipulator_builder {

    /** @var array $manipulators */
    private $manipulators = [
        deleter::class,
        puller::class,
        pusher::class,
        recoverer::class,
        checker::class,
    ];

    /** @var string $manipulatorclass */
    private $manipulatorclass;

    /** @var candidates_finder $finder */
    private $finder;

    /** @var stdClass $config  */
    private $config;

    /**
     * @param string $manipulator
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function execute($manipulator) {
        if (!$this->build($manipulator)) {
            return;
        }
        $logger = new aggregate_logger();
        $candidates = $this->finder->get();
        $countcandidates = count($candidates);
        $logger->log_object_query($this->finder->get_query_name(), $countcandidates);
        if ($countcandidates === 0) {
            mtrace('No candidate objects found.');
            return;
        }

        $filesystem = new $this->config->filesystem();
        if (!$filesystem->get_client_availability()) {
            mtrace(get_string('client_not_available', 'tool_objectfs'));
            return;
        }
        $manipulator = new $this->manipulatorclass($filesystem, $this->config, $logger);
        $manipulator->execute($candidates);
    }

    /**
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function execute_all() {
        foreach ($this->manipulators as $manipulator) {
            mtrace("Executing objectfs $manipulator");
            $this->execute($manipulator);
            mtrace("Objectfs $manipulator successfully executed");
        }
    }

    /**
     * @param string $manipulator
     * @return bool
     * @throws moodle_exception
     */
    private function build($manipulator) {
        $this->config = get_objectfs_config();
        $this->manipulatorclass = $manipulator;
        $this->finder = new candidates_finder($manipulator, $this->config);
        return true;
    }
}
