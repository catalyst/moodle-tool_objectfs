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

require_once($CFG->libdir . "/formslib.php");

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
        $mform = $this->_form;

        $config = $this->_customdata['config'];

        $regionoptions = array ('us-east-1',
                                'us-east-2',
                                'us-west-1',
                                'us-west-2',
                                'ap-northeast-2',
                                'ap-southeast-1',
                                'ap-southeast-2',
                                'ap-northeast-1',
                                'eu-central-1',
                                'eu-west-1');

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

        $mform->addElement('text', 'secret', get_string('settings:secret', 'tool_sssfs'));
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

        $mform->addElement('button', 'checkconnection', get_string('settings:checkconnenction', 'tool_sssfs'));

        $mform->addElement('header', 'filetransferheader', get_string('settings:filetransferheader', 'tool_sssfs'));

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

        $mform->addElement('text', 'consistancydelay', get_string('settings:consistancydelay', 'tool_sssfs'));
        $mform->addHelpButton('consistancydelay', 'settings:consistancydelay', 'tool_sssfs');
        $mform->setType("consistancydelay", PARAM_INT);
        if (isset($config->consistancydelay)) {
            $mform->setDefault('consistancydelay', $config->consistancydelay);
        }

        $mform->addElement('header', 'loggingheader', get_string('settings:loggingheader', 'tool_sssfs'));

        $mform->addElement('text', 'logginglocation', get_string('settings:logginglocation', 'tool_sssfs'));
        $mform->addHelpButton('logginglocation', 'settings:logginglocation', 'tool_sssfs');
        $mform->setType("logginglocation", PARAM_SAFEPATH);
        if (isset($config->logginglocation)) {
            $mform->setDefault('logginglocation', $config->logginglocation);
        }

        $this->add_action_buttons();
    }

}

