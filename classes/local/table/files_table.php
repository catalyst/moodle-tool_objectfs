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
 * Missing files table.
 *
 * @package   tool_objectfs
 * @author    Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\local\table;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/tablelib.php');

/**
 * [Description files_table]
 */
class files_table extends \table_sql {

    /**
     * Constructor.
     *
     * @param string $uniqueid
     * @param string $objectlocation Status of the objects. Should be one of OBJECT_LOCATION_*
     */
    public function __construct($uniqueid, $objectlocation) {
        parent::__construct($uniqueid);

        $fields = 'f.*, ctx.instanceid, ctx.contextlevel';
        $from = '{files} f';
        $from .= ' LEFT JOIN {tool_objectfs_objects} o on f.contenthash = o.contenthash';
        $from .= ' LEFT JOIN {context} ctx ON f.contextid = ctx.id';
        $where = 'o.location = ?';
        $params = [$objectlocation];

        $this->columns = $this->headers = ['id', 'contextid', 'contenthash', 'localpath', 'link', 'component',
            'filearea', 'filename', 'filepath', 'mimetype', 'filesize', 'timecreated'];

        $this->no_sorting('localpath');
        $this->no_sorting('link');

        $this->define_columns($this->columns);
        $this->define_headers($this->headers);

        $this->set_sql($fields, $from, $where, $params);
        $this->set_count_sql("SELECT COUNT(*) FROM $from WHERE $where", $params);

        $this->downloadable = true;
    }

    /**
     * col_id
     * @param \stdClass $row
     * 
     * @return int
     */
    public function col_id(\stdClass $row) {
        return $row->id;
    }

    /**
     * col_contextid
     * @param \stdClass $row
     * 
     * @return int
     */
    public function col_contextid(\stdClass $row) {
        return $row->contextid;
    }

    /**
     * col_contenthash
     * @param \stdClass $row
     * 
     * @return string
     */
    public function col_contenthash(\stdClass $row) {
        return $row->contenthash;
    }

    /**
     * col_localpath
     * @param \stdClass $row
     * 
     * @return string
     */
    public function col_localpath(\stdClass $row) {
        $l1 = $row->contenthash[0] . $row->contenthash[1];
        $l2 = $row->contenthash[2] . $row->contenthash[3];

        return "$l1/$l2";
    }

    /**
     * col_component
     * @param \stdClass $row
     * 
     * @return mixed
     */
    public function col_component(\stdClass $row) {
        return $row->component;
    }

    /**
     * col_filearea
     * @param \stdClass $row
     * 
     * @return mixed
     */
    public function col_filearea(\stdClass $row) {
        return $row->filearea;
    }

    /**
     * col_filename
     * @param \stdClass $row
     * 
     * @return mixed
     */
    public function col_filename(\stdClass $row) {
        return $row->filename;
    }

    /**
     * col_filepath
     * @param \stdClass $row
     * 
     * @return string
     */
    public function col_filepath(\stdClass $row) {
        return $row->filepath;
    }

    /**
     * col_mimetype
     * @param \stdClass $row
     * 
     * @return string
     */
    public function col_mimetype(\stdClass $row) {
        return $row->mimetype;
    }

    /**
     * col_filesize
     * @param \stdClass $row
     * 
     * @return mixed
     */
    public function col_filesize(\stdClass $row) {
        return display_size($row->filesize);
    }

    /**
     * col_timecreated
     * @param \stdClass $row
     * 
     * @return int
     */
    public function col_timecreated(\stdClass $row) {
        return userdate($row->timecreated);
    }

    /**
     * col_link
     * @param \stdClass $row
     * 
     * @return string
     */
    public function col_link(\stdClass $row) {
        global $DB;

        if (substr($row->component, 0, strlen('mod_')) === "mod_") {
            switch ((int)$row->contextlevel) {
                case CONTEXT_MODULE:
                    list ($course, $cm) = get_course_and_cm_from_cmid($row->instanceid);
                    if (!empty($cm)) {
                        $url = new \moodle_url($cm->url);
                    }
                    break;
                case CONTEXT_COURSE:
                    $url = new \moodle_url("/course/view.php", ['id' => $row->instanceid]);
                    break;
                default:
                    // Do nothing for now, in the future this can handle other contexts.
            }
        } else if ($row->component === 'course') {
            if ($row->filearea === "legacy") {
                $params = ['contextid' => $row->contextid];
                $url = new \moodle_url("/files/index.php", $params);

            } else if ($row->filearea === "section") {
                $params = ['id' => $row->instanceid];
                $url = new \moodle_url("/course/view.php", $params);
            }

        } else if ($row->component === 'block_html') {
            // Internally blocks are different to course modules and require a different lookup.
            $bi = $DB->get_record('block_instances', ['id' => $row->instanceid]);
            $cctx = $DB->get_record('context', ['id' => $bi->parentcontextid]);
            $params = ['id' => $cctx->instanceid];
            $url = new \moodle_url("/course/view.php", $params);
        }

        if ($this->is_downloading()) {
            if (!empty($url)) {
                return $url->out(false);
            } else {
                return '';
            }
        }

        if (!empty($url)) {
            return \html_writer::link($url, $url);
        } else {
            return '';
        }
    }
}
