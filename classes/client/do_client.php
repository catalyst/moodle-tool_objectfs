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

namespace tool_objectfs\client;

defined('MOODLE_INTERNAL') || die();

$autoloader = $CFG->dirroot . '/local/aws/sdk/aws-autoloader.php';

if (!file_exists($autoloader)) {

    // Stub class with bare implementation for when the SDK prerequisite does not exist.
    class do_client {
        public function get_availability() {
            return false;
        }

        public function register_stream_wrapper() {
            return false;
        }
    }

    return;
}

require_once($autoloader);

use Aws\S3\S3Client;

class do_client extends s3_client {

    public function __construct($config) {
        $this->bucket = $config->do_space;
        $this->set_client($config);
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

        $mform = $this->define_amazon_s3_check($mform, false);

        $regionoptions = array(
            'sfo2'      => 'sfo2 (San Fransisco)',
            'nyc3'      => 'nyc3 (New York City)',
            'ams3'      => 'ams3 (Amsterdam)',
            'sgp1'      => 'spg1 (Singapore)',
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

        return $mform;
    }

}
