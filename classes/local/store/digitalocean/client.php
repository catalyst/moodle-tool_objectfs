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
 * @copyright Brian Yanosik
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\local\store\digitalocean;

use tool_objectfs\local\store\s3\client as s3_client;

/**
 * client
 */
class client extends s3_client {

    /**
     * construct
     * @param \stdClass $config
     * @return void
     */
    public function __construct($config) {
        global $CFG;
        $this->autoloader = $CFG->libdir . '/aws-sdk/src/functions.php';
        $this->testdelete = false;

        if ($this->get_availability() && !empty($config)) {
            require_once($this->autoloader);
            $this->bucket = $config->do_space;
            $this->set_client($config);
        } else {
            parent::__construct($config);
        }
    }

    /**
     * Check if the client configured properly.
     *
     * @param \stdClass $config Client config.
     * @return bool
     */
    protected function is_configured($config) {
        if (empty($config->do_key) || empty($config->do_secret) || empty($config->do_region)) {
            return false;
        }

        return true;
    }

    /**
     * set_client
     * @param \stdClass $config
     *
     * @return void
     */
    public function set_client($config) {
        if (!$this->is_configured($config)) {
            $this->client = null;
            return;
        }

        $this->client = \Aws\S3\S3Client::factory([
            'credentials' => ['key' => $config->do_key, 'secret' => $config->do_secret],
            'region' => $config->do_region,
            'endpoint' => 'https://' . $config->do_region . '.digitaloceanspaces.com',
            'version' => AWS_API_VERSION,
        ]);
    }

    /**
     * define_client_section
     * @param admin_settingpage $settings
     * @param \stdClass $config
     * @return admin_settingpage
     */
    public function define_client_section($settings, $config) {

        $regionoptions = [
            'sfo2'      => 'sfo2 (San Fransisco)',
            'nyc3'      => 'nyc3 (New York City)',
            'ams3'      => 'ams3 (Amsterdam)',
            'sgp1'      => 'spg1 (Singapore)',
            'fra1'      => 'fra1 (Frankfurt)',
        ];

        $settings->add(new \admin_setting_heading('tool_objectfs/do',
            new \lang_string('settings:do:header', 'tool_objectfs'), ''));

        $settings->add(new \admin_setting_configtext('tool_objectfs/do_key',
            new \lang_string('settings:do:key', 'tool_objectfs'),
            new \lang_string('settings:do:key_help', 'tool_objectfs'), ''));

        $settings->add(new \admin_setting_configpasswordunmask('tool_objectfs/do_secret',
            new \lang_string('settings:do:secret', 'tool_objectfs'),
            new \lang_string('settings:do:secret_help', 'tool_objectfs'), ''));

        $settings->add(new \admin_setting_configtext('tool_objectfs/do_space',
            new \lang_string('settings:do:space', 'tool_objectfs'),
            new \lang_string('settings:do:space_help', 'tool_objectfs'), ''));

        $settings->add(new \admin_setting_configselect('tool_objectfs/do_region',
            new \lang_string('settings:do:region', 'tool_objectfs'),
            new \lang_string('settings:do:region_help', 'tool_objectfs'), '', $regionoptions));

        return $settings;
    }

}
