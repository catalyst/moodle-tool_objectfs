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
 * S3 settings form
 *
 * @package   tool_sssfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_sssfs\form;

defined('MOODLE_INTERNAL') || die();

use tool_sssfs\sss_client;

require_once($CFG->libdir . "/formslib.php");

class settings_form extends \moodleform {

    /**
     * {@inheritDoc}
     * @see moodleform::definition()
     */
    public function definition() {

        global $OUTPUT;
        $mform = $this->_form;

        $config = $this->_customdata['config'];

        $connection = false;
        if (isset($config->key) && isset($config->secret) && isset($config->bucket)) {
            $client = new sss_client($config);
            $connection = $client->test_connection();
        }

        if ($connection) {
            $permissions = $client->permissions_check();
        }

        if (isset($config->sizethreshold)) {
            $config->sizethreshold = $config->sizethreshold / 1024; // Convert to KB.
        }

        $regionoptions = array( 'us-east-1'          => 'us-east-1',
                                'us-east-2'         => 'us-east-2',
                                'us-west-1'         => 'us-west-1',
                                'us-west-2'         => 'us-west-2',
                                'ap-northeast-2'    => 'ap-northeast-2',
                                'ap-southeast-1'    => 'ap-southeast-1',
                                'ap-southeast-2'    => 'ap-southeast-2',
                                'ap-northeast-1'    => 'ap-northeast-1',
                                'eu-central-1'      => 'eu-central-1',
                                'eu-west-1'         => 'eu-west-1');

        $defaults = array(
            'enabled'           => 0,
            'key'               => '',
            'secret'            => '',
            'bucket'            => '',
            'region'            => 'us-east-1',
            'sizethreshold'     => 1024 * 10,
            'minimumage'        => 7 * 24 * 60 * 60,
            'deletelocal'       => 0,
            'consistencydelay'  => 10 * 60,
            'maxtaskruntime'    => 60,
            'logging'           => 0,
            'prefersss'         => 0
        );

        $mform->addElement('advcheckbox', 'enabled', get_string('settings:enabled', 'tool_sssfs'));
        $mform->addHelpButton('enabled', 'settings:enabled', 'tool_sssfs');

        $mform->addElement('header', 'awsheader', get_string('settings:awsheader', 'tool_sssfs'));

        $mform->addElement('text', 'key', get_string('settings:key', 'tool_sssfs'));
        $mform->addHelpButton('key', 'settings:key', 'tool_sssfs');
        $mform->setType("key", PARAM_TEXT);

        $mform->addElement('passwordunmask', 'secret', get_string('settings:secret', 'tool_sssfs'), array('size' => 40));
        $mform->addHelpButton('secret', 'settings:secret', 'tool_sssfs');
        $mform->setType("secret", PARAM_TEXT);

        $mform->addElement('text', 'bucket', get_string('settings:bucket', 'tool_sssfs'));
        $mform->addHelpButton('bucket', 'settings:bucket', 'tool_sssfs');
        $mform->setType("bucket", PARAM_TEXT);

        $mform->addElement('select', 'region', get_string('settings:region', 'tool_sssfs'), $regionoptions);
        $mform->addHelpButton('region', 'settings:region', 'tool_sssfs');

        if ($connection) {
            $mform->addElement('html', $OUTPUT->notification(get_string('settings:connectionsuccess', 'tool_sssfs'), 'notifysuccess'));
        } else {
            $mform->addElement('html', $OUTPUT->notification(get_string('settings:connectionfailure', 'tool_sssfs'), 'notifyproblem'));
        }

        if ($permissions) {
            $errormsg = '';
            if (!$permissions[AWS_CAN_WRITE_OBJECT]) {
                $errormsg .= get_string('settings:writefailure', 'tool_sssfs');
            }

            if (!$permissions[AWS_CAN_READ_OBJECT]) {
                $errormsg .= get_string('settings:readfailure', 'tool_sssfs');
            }

            if ($permissions[AWS_CAN_DELETE_OBJECT]) {
                $errormsg .= get_string('settings:deletesuccess', 'tool_sssfs');
            }

            if (strlen($errormsg) > 0) {
                $mform->addElement('html', $OUTPUT->notification($errormsg, 'notifyproblem'));
            } else {
                $mform->addElement('html', $OUTPUT->notification(get_string('settings:permissioncheckpassed', 'tool_sssfs'), 'notifysuccess'));
            }
        }

        $mform->addElement('header', 'filetransferheader', get_string('settings:filetransferheader', 'tool_sssfs'));
        $mform->setExpanded('filetransferheader');

        $mform->addElement('text', 'sizethreshold', get_string('settings:sizethreshold', 'tool_sssfs'));
        $mform->addHelpButton('sizethreshold', 'settings:sizethreshold', 'tool_sssfs');
        $mform->setType("sizethreshold", PARAM_INT);

        $mform->addElement('duration', 'minimumage', get_string('settings:minimumage', 'tool_sssfs'));
        $mform->addHelpButton('minimumage', 'settings:minimumage', 'tool_sssfs');
        $mform->setType("minimumage", PARAM_INT);

        $mform->addElement('duration', 'maxtaskruntime', get_string('settings:maxtaskruntime', 'tool_sssfs'));
        $mform->addHelpButton('maxtaskruntime', 'settings:maxtaskruntime', 'tool_sssfs');
        $mform->setType("maxtaskruntime", PARAM_INT);

        $mform->addElement('advcheckbox', 'deletelocal', get_string('settings:deletelocal', 'tool_sssfs'));
        $mform->addHelpButton('deletelocal', 'settings:deletelocal', 'tool_sssfs');
        $mform->setType("deletelocal", PARAM_INT);

        $mform->addElement('duration', 'consistencydelay', get_string('settings:consistencydelay', 'tool_sssfs'));
        $mform->addHelpButton('consistencydelay', 'settings:consistencydelay', 'tool_sssfs');
        $mform->disabledIf('consistencydelay', 'deletelocal');
        $mform->setType("consistencydelay", PARAM_INT);

        $mform->addElement('advcheckbox', 'prefersss', get_string('settings:prefersss', 'tool_sssfs'));
        $mform->addHelpButton('prefersss', 'settings:prefersss', 'tool_sssfs');
        $mform->setType("prefersss", PARAM_INT);

        $mform->addElement('header', 'loggingheader', get_string('settings:loggingheader', 'tool_sssfs'));
        $mform->setExpanded('loggingheader');

        $mform->addElement('advcheckbox', 'logging', get_string('settings:logging', 'tool_sssfs'));
        $mform->addHelpButton('logging', 'settings:logging', 'tool_sssfs');
        $mform->setType("logging", PARAM_INT);

        foreach ($defaults as $key => $value) {
            if (isset($config->$key)) {
                $mform->setDefault($key, $config->$key);
            } else {
                $mform->setDefault($key, $value);
            }
        }

        $this->add_action_buttons();
    }

}

