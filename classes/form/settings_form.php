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
 * Task that pushes files to S3.
 *
 * @package   tool_sssfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_sssfs\form;

use tool_sssfs\sss_client;

require_once($CFG->libdir . "/formslib.php");

defined('MOODLE_INTERNAL') || die();

/**
 * Form for editing an Enviroment bar.
 *
 * @copyright Catalyst IT
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
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

        $regionoptions = array ('us-east-1' => 'us-east-1',
                                'us-east-2' => 'us-east-2',
                                'us-west-1' => 'us-west-1',
                                'us-west-2' => 'us-west-2',
                                'ap-northeast-2' => 'ap-northeast-2',
                                'ap-southeast-1' => 'ap-southeast-1',
                                'ap-southeast-2' => 'ap-southeast-2',
                                'ap-northeast-1' => 'ap-northeast-1',
                                'eu-central-1' => 'eu-central-1',
                                'eu-west-1' => 'eu-west-1');

        $mform->addElement('advcheckbox', 'enabled', get_string('settings:enabled', 'tool_sssfs'));
        $mform->addHelpButton('enabled', 'settings:enabled', 'tool_sssfs');
        if (isset($config->enabled)) {
            $mform->setDefault('enabled', $config->enabled);
        }

        $mform->addElement('header', 'awsheader', get_string('settings:awsheader', 'tool_sssfs'));

        $mform->addElement('text', 'key', get_string('settings:key', 'tool_sssfs'));
        $mform->addHelpButton('key', 'settings:key', 'tool_sssfs');
        $mform->setType("key", PARAM_TEXT);
        if (isset($config->key)) {
            $mform->setDefault('key', $config->key);
        }

        $mform->addElement('passwordunmask', 'secret', get_string('settings:secret', 'tool_sssfs'), array('size' => 40));
        $mform->addHelpButton('secret', 'settings:secret', 'tool_sssfs');
        $mform->setType("secret", PARAM_TEXT);
        if (isset($config->secret)) {
            $mform->setDefault('secret', $config->secret);
        }

        $mform->addElement('text', 'bucket', get_string('settings:bucket', 'tool_sssfs'));
        $mform->addHelpButton('bucket', 'settings:bucket', 'tool_sssfs');
        $mform->setType("bucket", PARAM_TEXT);
        if (isset($config->bucket)) {
            $mform->setDefault('bucket', $config->bucket);
        }

        $mform->addElement('select', 'region', get_string('settings:region', 'tool_sssfs'), $regionoptions);
        $mform->addHelpButton('region', 'settings:region', 'tool_sssfs');
        if (isset($config->region)) {
            $mform->setDefault('region', $config->region);
        }

        if ($connection) {
            $mform->addElement('html', $OUTPUT->notification(get_string('settings:connectionsuccess', 'tool_sssfs'), 'notifysuccess'));
        } else {
            $mform->addElement('html', $OUTPUT->notification(get_string('settings:connectionfailure', 'tool_sssfs'), 'notifyfailure'));
        }

        $mform->addElement('header', 'filetransferheader', get_string('settings:filetransferheader', 'tool_sssfs'));
        $mform->setExpanded('filetransferheader');

        $mform->addElement('text', 'sizethreshold', get_string('settings:sizethreshold', 'tool_sssfs'));
        $mform->addHelpButton('sizethreshold', 'settings:sizethreshold', 'tool_sssfs');
        $mform->setType("sizethreshold", PARAM_INT);
        if (isset($config->sizethreshold)) {
            $mform->setDefault('sizethreshold', $config->sizethreshold);
        }

        $mform->addElement('duration', 'minimumage', get_string('settings:minimumage', 'tool_sssfs'));
        $mform->addHelpButton('minimumage', 'settings:minimumage', 'tool_sssfs');
        $mform->setType("minimumage", PARAM_INT);
        if (isset($config->minimumage)) {
            $mform->setDefault('minimumage', $config->minimumage);
        }

        $mform->addElement('text', 'consistencydelay', get_string('settings:consistencydelay', 'tool_sssfs'));
        $mform->addHelpButton('consistencydelay', 'settings:consistencydelay', 'tool_sssfs');
        $mform->setType("consistencydelay", PARAM_INT);
        if (isset($config->consistencydelay)) {
            $mform->setDefault('consistencydelay', $config->consistencydelay);
        }

        $mform->addElement('duration', 'maxtaskruntime', get_string('settings:maxtaskruntime', 'tool_sssfs'));
        $mform->addHelpButton('maxtaskruntime', 'settings:maxtaskruntime', 'tool_sssfs');
        $mform->setType("maxtaskruntime", PARAM_INT);
        if (isset($config->minimumage)) {
            $mform->setDefault('maxtaskruntime', $config->minimumage);
        }

        $mform->addElement('header', 'loggingheader', get_string('settings:loggingheader', 'tool_sssfs'));
        $mform->setExpanded('loggingheader');

        $mform->addElement('advcheckbox', 'logging', get_string('settings:logging', 'tool_sssfs'));
        $mform->addHelpButton('logging', 'settings:logging', 'tool_sssfs');
        $mform->setType("logging", PARAM_INT);
        if (isset($config->logging)) {
            $mform->setDefault('logging', $config->logging);
        }

        $this->add_action_buttons();
    }

}

