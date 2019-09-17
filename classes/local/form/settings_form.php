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

namespace tool_objectfs\local\form;

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

        $link = \html_writer::link(new \moodle_url('/admin/tool/objectfs/object_status.php'),
            get_string('object_status:page', 'tool_objectfs'));

        $mform->addElement('html', $OUTPUT->heading($link, 5));

        $mform = $this->define_general_section($mform);
        $mform = $this->define_file_transfer_section($mform);
        $mform = $this->define_client_selection($mform, $config);
        if ($config->filesystem !== '') {
            $mform = $this->define_client_section($mform, $config);
        }

        if (tool_objectfs_filesystem_supports_presigned_urls($config->filesystem)) {
            $mform = $this->define_presignedurl_section($mform, $config);
        } else {
            // TODO: set enablepresignedurls = 0.
            $mform->setDefault("enablepresignedurls", 0);
        }

        $mform = $this->define_testing_section($mform, $config);

        foreach ($config as $key => $value) {
            $mform->setDefault($key, $value);
        }

        $this->add_action_buttons();
    }

    public function define_testing_section($mform) {
        global $OUTPUT;
        $mform->addElement('header', 'testingheader', get_string('settings:testingheader', 'tool_objectfs'));
        $mform->setExpanded('testingheader', false);

        $alert = get_string('settings:testingdescr', 'tool_objectfs');
        $mform->addElement('html', $OUTPUT->notification($alert, 'warning'));

        $mform->addElement('advcheckbox', 'preferexternal', get_string('settings:preferexternal', 'tool_objectfs'));
        $mform->addHelpButton('preferexternal', 'settings:preferexternal', 'tool_objectfs');
        $mform->setType("preferexternal", PARAM_INT);

        return $mform;
    }

    public function define_general_section($mform) {
        $mform->addElement('header', 'generalheader', get_string('settings:generalheader', 'tool_objectfs'));
        $mform->setExpanded('generalheader');

        $mform->addElement('advcheckbox', 'enabletasks', get_string('settings:enabletasks', 'tool_objectfs'));
        $mform->addHelpButton('enabletasks', 'settings:enabletasks', 'tool_objectfs');

        $mform->addElement('duration', 'maxtaskruntime', get_string('settings:maxtaskruntime', 'tool_objectfs'));
        $mform->addHelpButton('maxtaskruntime', 'settings:maxtaskruntime', 'tool_objectfs');
        $mform->disabledIf('maxtaskruntime', 'enabletasks');
        $mform->setType("maxtaskruntime", PARAM_INT);

        $mform->addElement('advcheckbox', 'enablelogging', get_string('settings:enablelogging', 'tool_objectfs'));
        $mform->addHelpButton('enablelogging', 'settings:enablelogging', 'tool_objectfs');

        return $mform;
    }

    public function define_file_transfer_section($mform) {
        $mform->addElement('header', 'filetransferheader', get_string('settings:filetransferheader', 'tool_objectfs'));
        $mform->setExpanded('filetransferheader');

        $mform->addElement('text', 'sizethreshold', get_string('settings:sizethreshold', 'tool_objectfs'));
        $mform->addHelpButton('sizethreshold', 'settings:sizethreshold', 'tool_objectfs');
        $mform->setType("sizethreshold", PARAM_INT);

        $mform->addElement('text', 'batchsize', get_string('settings:batchsize', 'tool_objectfs'));
        $mform->addHelpButton('batchsize', 'settings:batchsize', 'tool_objectfs');
        $mform->setType("batchsize", PARAM_INT);

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
        global $OUTPUT;

        $client = tool_objectfs_get_client($config);

        if ($client and $client->get_availability()) {
            $mform = $client->define_client_section($mform, $config);
        } else {
            $errstr = get_string('settings:clientnotavailable', 'tool_objectfs');
            $mform->addElement('html', $OUTPUT->notification($errstr, 'notifyproblem'));
        }

        return $mform;
    }

    public function define_client_selection($mform, $config) {
        global $CFG, $OUTPUT;

        $mform->addElement('header', 'clientselectionheader', get_string('settings:clientselection:header', 'tool_objectfs'));
        $mform->setExpanded('clientselectionheader');

        $fslist = tool_objectfs_get_fs_list();
        $mform->addElement('select', 'filesystem', get_string('settings:clientselection:title', 'tool_objectfs'), $fslist);
        $mform->addHelpButton('filesystem', 'settings:clientselection:title', 'tool_objectfs');

        if (isset($CFG->alternative_file_system_class)) {
            if ($CFG->alternative_file_system_class != $config->filesystem) {
                $string = get_string('settings:clientselection:mismatchfilesystem', 'tool_objectfs');
                $mform->addElement('html', $OUTPUT->notification($string, 'notifyproblem'));
            }
        } else {
            $string = get_string('settings:clientselection:filesystemnotdefined', 'tool_objectfs');
            $mform->addElement('html', $OUTPUT->notification($string, 'warning'));
        }

        return $mform;
    }

    public function define_presignedurl_section($mform) {
        global $OUTPUT;

        $mform->addElement('header', 'presignedurlheader',
            get_string('settings:presignedurl:header', 'tool_objectfs'));
        $mform->setExpanded('presignedurlheader');

        $link = \html_writer::link(new \moodle_url('/admin/tool/objectfs/presignedurl_tests.php'),
            get_string('presignedurl_testing:page', 'tool_objectfs'));

        $text = $OUTPUT->heading(get_string('settings:presignedurl:warning', 'tool_objectfs').$link, 6);
        $mform->addElement('html', $OUTPUT->notification($text, 'warning'));

        $mform->addElement('advcheckbox', 'enablepresignedurls',
            get_string('settings:presignedurl:enablepresignedurls', 'tool_objectfs'));
        $mform->addHelpButton('enablepresignedurls', 'settings:presignedurl:enablepresignedurls', 'tool_objectfs');
        $mform->setType("enablepresignedurls", PARAM_INT);

        $mform->addElement('duration', 'expirationtime',
            get_string('settings:presignedurl:expirationtime', 'tool_objectfs'));
        $mform->addHelpButton('expirationtime', 'settings:presignedurl:expirationtime', 'tool_objectfs');
        $mform->setType("expirationtime", PARAM_INT);

        $mform->addElement('text', 'presignedminfilesize',
            get_string('settings:presignedurl:presignedminfilesize', 'tool_objectfs'));
        $mform->addHelpButton('presignedminfilesize', 'settings:presignedurl:presignedminfilesize', 'tool_objectfs');
        $mform->setType("presignedminfilesize", PARAM_INT);

        return $mform;
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (is_numeric($data['maxtaskruntime']) && $data['maxtaskruntime'] < 0 ) {
            $errors['maxtaskruntime'] = get_string('settings:error:numeric', 'tool_objectfs');
        }

        if (is_numeric($data['sizethreshold']) && $data['sizethreshold'] < 0 ) {
            $errors['sizethreshold'] = get_string('settings:error:numeric', 'tool_objectfs');
        }

        if (is_numeric($data['batchsize']) && $data['batchsize'] < 0 ) {
            $errors['batchsize'] = get_string('settings:error:numeric', 'tool_objectfs');
        }

        if (is_numeric($data['minimumage']) && $data['minimumage'] < 0 ) {
            $errors['minimumage'] = get_string('settings:error:numeric', 'tool_objectfs');
        }

        if (is_numeric($data['consistencydelay']) && $data['consistencydelay'] < 0 ) {
            $errors['consistencydelay'] = get_string('settings:error:numeric', 'tool_objectfs');
        }

        if (is_numeric($data['expirationtime']) && $data['expirationtime'] < 0 ) {
            $errors['expirationtime'] = get_string('settings:error:numeric', 'tool_objectfs');
        }

        if (is_numeric($data['presignedminfilesize']) && $data['presignedminfilesize'] < 0 ) {
            $errors['presignedminfilesize'] = get_string('settings:error:numeric', 'tool_objectfs');
        }

        return $errors;
    }
}
