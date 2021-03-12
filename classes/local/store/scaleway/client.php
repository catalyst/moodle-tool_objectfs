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
 * Scaleway Spaces client.
 *
 * @package   tool_objectfs
 * @author    Alberto Buratti <alberto.buratti@gtsu.org>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\local\store\scaleway;

defined('MOODLE_INTERNAL') || die();

use tool_objectfs\local\store\s3\client as s3_client;

class client extends s3_client {

    public function __construct($config) {
        global $CFG;
        $this->autoloader = $CFG->dirroot . '/local/aws/sdk/aws-autoloader.php';
        $this->testdelete = false;

        if ($this->get_availability() && !empty($config)) {
            require_once($this->autoloader);
            $this->bucket = $config->scw_space;
            $this->set_client($config);
        } else {
            parent::__construct($config);
        }
    }

    public function set_client($config) {
        $this->client = \Aws\S3\S3Client::factory(array(
            'credentials' => array('key' => $config->scw_key, 'secret' => $config->scw_secret),
            'region' => $config->scw_region,
            'endpoint' => 'https://s3.' . $config->scw_region . '.scw.cloud',
            'version' => AWS_API_VERSION
        ));
    }

    /**
     * @param admin_settingpage $settings
     * @param $config
     * @return admin_settingpage
     */
    public function define_client_section($settings, $config) {

        $regionoptions = array(
            'nl-ams'      => 'nl-ams (Amsterdam, The Netherlands)',
            'fr-par'      => 'fr-par (Paris, France)',
            'pl-waw'      => 'pl-waw (Warsaw, Poland)',
        );

        $settings->add(new \admin_setting_heading('tool_objectfs/scw',
            new \lang_string('settings:scw:header', 'tool_objectfs'), ''));

        $settings->add(new \admin_setting_configtext('tool_objectfs/scw_key',
            new \lang_string('settings:scw:key', 'tool_objectfs'),
            new \lang_string('settings:scw:key_help', 'tool_objectfs'), ''));

        $settings->add(new \admin_setting_configpasswordunmask('tool_objectfs/scw_secret',
            new \lang_string('settings:scw:secret', 'tool_objectfs'),
            new \lang_string('settings:scw:secret_help', 'tool_objectfs'), ''));

        $settings->add(new \admin_setting_configtext('tool_objectfs/scw_space',
            new \lang_string('settings:scw:space', 'tool_objectfs'),
            new \lang_string('settings:scw:space_help', 'tool_objectfs'), ''));

        $settings->add(new \admin_setting_configselect('tool_objectfs/scw_region',
            new \lang_string('settings:scw:region', 'tool_objectfs'),
            new \lang_string('settings:scw:region_help', 'tool_objectfs'), '', $regionoptions));

        return $settings;
    }

}
