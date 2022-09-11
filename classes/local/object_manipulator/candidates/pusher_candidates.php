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
 * Class pusher_candidates
 * @package tool_objectfs
 * @author Gleimer Mora <gleimermora@catalyst-au.net>
 * @copyright Catalyst IT
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\local\object_manipulator\candidates;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/admin/tool/objectfs/tests/classes/test_client.php');

class pusher_candidates extends manipulator_candidates_base {

    /** @var string $queryname */
    protected $queryname = 'get_push_candidates';

    /**
     * @inheritDoc
     * @return string
     */
    public function get_candidates_sql() {

        // Filter file component.
        $filtercomponent = [];
        if (!empty($this->config->filtercomponent)) {
            foreach(explode(',', $this->config->filtercomponent) as $item){
                if(!empty(trim($item))){
                    $filtercomponent[] = trim($item);
                }
            }

            $filtercomponent = array_unique($filtercomponent);
        }

        $strcomponent = !empty($filtercomponent) ? " AND f.component IN ('".implode("','", $filtercomponent)."') " : '';

        // Filter file filearea.
        $filterfilearea = [];
        if (!empty($this->config->filterfilearea)) {
            foreach(explode(',', $this->config->filterfilearea) as $item){
                if(!empty(trim($item))){
                    $filterfilearea[] = trim($item);
                }
            }

            $filterfilearea = array_unique($filterfilearea);
        }

        $strfilearea = !empty($filterfilearea) ? " AND f.filearea IN ('".implode("','", $filterfilearea)."') " : '';

        // Filter file mimetype.
        $filtermimetype = [];
        if (!empty($this->config->filtermimetype)) {
            foreach(explode(',', $this->config->filtermimetype) as $item){
                if(!empty(trim($item))){
                    $filtermimetype[] = trim($item);
                }
            }

            $filtermimetype = array_unique($filtermimetype);
        }

        $strmimetype = !empty($filtermimetype) ? " AND f.mimetype IN ('".implode("','", $filtermimetype)."') " : '';

        return 'SELECT MAX(f.id),
                       f.contenthash,
                       MAX(f.filesize) AS filesize
                  FROM {files} f
                  JOIN {tool_objectfs_objects} o ON f.contenthash = o.contenthash
                 WHERE f.filesize > :threshold
                   '.$strcomponent.'
                   '.$strfilearea.'
                   '.$strmimetype.'
                   AND f.filesize < :maximum_file_size
                   AND f.timecreated <= :maxcreatedtimestamp
                   AND o.location = :object_location
              GROUP BY f.contenthash, o.location';
    }

    /**
     * @inheritDoc
     * @return array
     */
    public function get_candidates_sql_params() {
        $filesystem = new $this->config->filesystem;
        return [
            'maxcreatedtimestamp' => time() - $this->config->minimumage,
            'threshold' => $this->config->sizethreshold,
            'component' => $this->config->component,
            'filearea' => $this->config->filearea,
            'mimetype' => $this->config->mimetype,
            'maximum_file_size' => $filesystem->get_maximum_upload_filesize(),
            'object_location' => OBJECT_LOCATION_LOCAL,
        ];
    }
}
