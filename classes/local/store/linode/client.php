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
 * Linode Object Spaces client.
 *
 * @package   tool_objectfs
 * @author    Brian Yanosik <kisonay@gmail.com>
 * @copyright Brian Yanosik
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\local\store\linode;

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
        $this->autoloader = $CFG->dirroot . '/local/aws/sdk/aws-autoloader.php';
        $this->testdelete = false;

        if ($this->get_availability() && !empty($config)) {
            require_once($this->autoloader);
            $this->bucket = $config->linode_bucket;
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
        if (empty($config->linode_key) || empty($config->linode_secret) || empty($config->linode_region)) {
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
            'credentials' => ['key' => $config->linode_key, 'secret' => $config->linode_secret],
            'region' => $config->linode_region,
            'endpoint' => 'https://' . $config->linode_region . '.linodeobjects.com',
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
            'nl-ams-1' => 'nl-ams-1 (Amsterdam, Netherlands)',
            'us-southeast-1' => 'us-southeast-1 (Atlanta, GA, USA)',
            'in-maa-1' => 'in-maa-1 (Chennai, India)',
            'us-ord-1' => 'us-ord-1 (Chicago, IL, USA)',
            'eu-central-1' => 'eu-central-1 (Frankfurt, Germany)',
            'id-cgk-1' => 'id-cgk-1 (Jakarta, Indonesia)',
            'us-lax-1' => 'us-lax-1 (Los Angeles, CA, USA)',
            'es-mad-1' => 'es-mad-1 (Madrid, Spain)',
            'us-mia-1' => 'us-mia-1 (Miami, FL, USA)',
            'it-mil-1' => 'it-mil-1 (Milan, Italy)',
            'us-east-1' => 'us-east-1 (Newark, NJ, USA)',
            'jp-osa-1' => 'jp-osa-1 (Osaka, Japan)',
            'fr-par-1' => 'fr-par-1 (Paris, France)',
            'br-gru-1' => 'br-gru-1 (São Paulo, Brazil)',
            'us-sea-1' => 'us-sea-1 (Seattle, WA, USA)',
            'ap-south-1' => 'ap-south-1 (Singapore)',
            'se-sto-1' => 'se-sto-1 (Stockholm, Sweden)',
            'us-iad-1' => 'us-iad-1 (Washington, DC, USA)',
        ];

        $settings->add(new \admin_setting_heading('tool_objectfs/linode',
            new \lang_string('settings:linode:header', 'tool_objectfs'), ''));

        $settings->add(new \admin_setting_configtext('tool_objectfs/linode_key',
            new \lang_string('settings:linode:key', 'tool_objectfs'),
            new \lang_string('settings:linode:key_help', 'tool_objectfs'), ''));

        $settings->add(new \admin_setting_configpasswordunmask('tool_objectfs/linode_secret',
            new \lang_string('settings:linode:secret', 'tool_objectfs'),
            new \lang_string('settings:linode:secret_help', 'tool_objectfs'), ''));

        $settings->add(new \admin_setting_configtext('tool_objectfs/linode_bucket',
            new \lang_string('settings:linode:bucket', 'tool_objectfs'),
            new \lang_string('settings:linode:bucket_help', 'tool_objectfs'), ''));

        $settings->add(new \admin_setting_configselect('tool_objectfs/linode_region',
            new \lang_string('settings:linode:region', 'tool_objectfs'),
            new \lang_string('settings:linode:region_help', 'tool_objectfs'), '', $regionoptions));

        return $settings;
    }

}
