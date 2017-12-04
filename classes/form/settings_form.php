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
 * Objectfs settings form
 *
 * @package   tool_objectfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . "/formslib.php");
require_once($CFG->dirroot . '/admin/tool/objectfs/lib.php');

class settings_form extends \moodleform {

    /**
     * {@inheritDoc}
     * @see moodleform::definition()
     */
    public function definition() {
        global $OUTPUT;
        $mform = $this->_form;
        $config = $this->_customdata['config'];

        $link = \html_writer::link(new \moodle_url('/admin/tool/objectfs/object_status.php'), get_string('object_status:page', 'tool_objectfs'));

        $mform->addElement('html', $OUTPUT->heading($link, 5));

        $mform = $this->define_cfg_check($mform, $config);
        $mform = $this->define_general_section($mform, $config);
        $mform = $this->define_file_transfer_section($mform, $config);
        $mform = $this->define_client_selection($mform, $config);
        $mform = $this->define_client_section($mform, $config);

        foreach ($config as $key => $value) {
            $mform->setDefault($key, $value);
        }

        $this->add_action_buttons();
    }

    public function define_cfg_check($mform, $config) {
        global $CFG, $OUTPUT;
        if (!isset($CFG->alternative_file_system_class)) {
            $mform->addElement('html', $OUTPUT->notification(get_string('settings:handlernotset', 'tool_objectfs'), 'notifyproblem'));
        }
        return $mform;
    }

    public function define_general_section($mform, $config) {
        $mform->addElement('header', 'generalheader', get_string('settings:generalheader', 'tool_objectfs'));
        $mform->setExpanded('generalheader');

        $mform->addElement('advcheckbox', 'enabletasks', get_string('settings:enabletasks', 'tool_objectfs'));
        $mform->addHelpButton('enabletasks', 'settings:enabletasks', 'tool_objectfs');

        $mform->addElement('duration', 'maxtaskruntime', get_string('settings:maxtaskruntime', 'tool_objectfs'));
        $mform->addHelpButton('maxtaskruntime', 'settings:maxtaskruntime', 'tool_objectfs');
        $mform->disabledIf('maxtaskruntime', 'enabletasks');
        $mform->setType("maxtaskruntime", PARAM_INT);

        $mform->addElement('advcheckbox', 'preferexternal', get_string('settings:preferexternal', 'tool_objectfs'));
        $mform->addHelpButton('preferexternal', 'settings:preferexternal', 'tool_objectfs');
        $mform->setType("preferexternal", PARAM_INT);

        $mform->addElement('advcheckbox', 'enablelogging', get_string('settings:enablelogging', 'tool_objectfs'));
        $mform->addHelpButton('enablelogging', 'settings:enablelogging', 'tool_objectfs');

        return $mform;
    }

    public function define_file_transfer_section($mform, $config) {
        $mform->addElement('header', 'filetransferheader', get_string('settings:filetransferheader', 'tool_objectfs'));
        $mform->setExpanded('filetransferheader');

        $mform->addElement('text', 'sizethreshold', get_string('settings:sizethreshold', 'tool_objectfs'));
        $mform->addHelpButton('sizethreshold', 'settings:sizethreshold', 'tool_objectfs');
        $mform->setType("sizethreshold", PARAM_INT);

        $mform->addElement('duration', 'minimumage', get_string('settings:minimumage', 'tool_objectfs'));
        $mform->addHelpButton('minimumage', 'settings:minimumage', 'tool_objectfs');
        $mform->setType("minimumage", PARAM_INT);

        $mform->addElement('advcheckbox', 'deletelocal', get_string('settings:deletelocal', 'tool_objectfs'));
        $mform->addHelpButton('deletelocal', 'settings:deletelocal', 'tool_objectfs');
        $mform->setType("deletelocal", PARAM_INT);

        $mform->addElement('duration', 'consistencydelay', get_string('settings:consistencydelay', 'tool_objectfs'));
        $mform->addHelpButton('consistencydelay', 'settings:consistencydelay', 'tool_objectfs');
        $mform->disabledIf('consistencydelay', 'deletelocal');
        $mform->setType("consistencydelay", PARAM_INT);
        return $mform;
    }

    public function define_client_section($mform, $config) {
        global $CFG, $OUTPUT;

        $clients = tool_objectfs_get_client_components('client');

        foreach ($clients as $name) {
            $client = new $name($config);

            if ($client->get_availability()) {
                $mform = $client->define_client_section($mform, $config);
            } else {
                $errstr = get_string('settings:clientnotavailable', 'tool_objectfs', $name);
                $mform->addElement('html', $OUTPUT->notification($errstr, 'notifyproblem'));
            }
        }

        return $mform;
    }

    public function define_client_selection($mform, $config) {
        global $CFG, $OUTPUT;

        $mform->addElement('header', 'clientselectionheader', get_string('settings:clientselection:header', 'tool_objectfs'));
        $mform->setExpanded('clientselectionheader');

        if (isset($CFG->alternative_file_system_class) && $CFG->alternative_file_system_class == $config->filesystem) {
            $mform->addElement('html', $OUTPUT->notification(get_string('settings:clientselection:matchfilesystem', 'tool_objectfs'), 'notifysuccess'));
        } else {
            $mform->addElement('html', $OUTPUT->notification(get_string('settings:clientselection:mismatchfilesystem', 'tool_objectfs'), 'notifyproblem'));
        }

        $names = tool_objectfs_get_client_components('file_system');
        $clientlist = array_combine($names, $names);

        $mform->addElement('select', 'filesystem', get_string('settings:clientselection:title', 'tool_objectfs'), $clientlist);
        $mform->addHelpButton('filesystem', 'settings:clientselection:title', 'tool_objectfs');
        return $mform;
    }
}

