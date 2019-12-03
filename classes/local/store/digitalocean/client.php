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
 * DigitalOcean Spaces client.
 *
 * @package   tool_objectfs
 * @author    Brian Yanosik <kisonay@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\local\store\digitalocean;

defined('MOODLE_INTERNAL') || die();

use Aws\S3\S3Client;
use tool_objectfs\local\store\s3\client as s3_client;

class client extends s3_client {

    public function __construct($config) {
        global $CFG;
        $this->autoloader = $CFG->dirroot . '/local/aws/sdk/aws-autoloader.php';

        if ($this->get_availability() && !empty($config)) {
            require_once($this->autoloader);
            $this->bucket = $config->do_space;
            $this->set_client($config);
        } else {
            parent::__construct($config);
        }
    }

    public function set_client($config) {
        $this->client = S3Client::factory(array(
            'credentials' => array('key' => $config->do_key, 'secret' => $config->do_secret),
            'region' => $config->do_region,
            'endpoint' => 'https://' . $config->do_region . '.digitaloceanspaces.com',
            'version' => AWS_API_VERSION
        ));
    }

    public function define_client_section($mform, $config) {

        $mform->addElement('header', 'doheader', get_string('settings:do:header', 'tool_objectfs'));
        $mform->setExpanded('doheader');

        $regionoptions = array(
            'sfo2'      => 'sfo2 (San Fransisco)',
            'nyc3'      => 'nyc3 (New York City)',
            'ams3'      => 'ams3 (Amsterdam)',
            'sgp1'      => 'spg1 (Singapore)',
            'fra1'      => 'fra1 (Frankfurt)',
        );

        $mform->addElement('text', 'do_key', get_string('settings:do:key', 'tool_objectfs'));
        $mform->addHelpButton('do_key', 'settings:do:key', 'tool_objectfs');
        $mform->setType("do_key", PARAM_TEXT);

        $mform->addElement('passwordunmask', 'do_secret', get_string('settings:do:secret', 'tool_objectfs'), array('size' => 40));
        $mform->addHelpButton('do_secret', 'settings:do:secret', 'tool_objectfs');
        $mform->setType("do_secret", PARAM_TEXT);

        $mform->addElement('text', 'do_space', get_string('settings:do:space', 'tool_objectfs'));
        $mform->addHelpButton('do_space', 'settings:do:space', 'tool_objectfs');
        $mform->setType("do_space", PARAM_TEXT);

        $mform->addElement('select', 'do_region', get_string('settings:do:region', 'tool_objectfs'), $regionoptions);
        $mform->addHelpButton('do_region', 'settings:do:region', 'tool_objectfs');

        $mform = $this->define_amazon_s3_check($mform, false);

        return $mform;
    }

}
