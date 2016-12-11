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
 * S3 client.
 *
 * @package   tool_sssfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_sssfs;

use Aws\S3\S3Client;

require_once(__DIR__ . '/../sdk/aws-autoloader.php');

defined('MOODLE_INTERNAL') || die();

define('AWS_API_VERSION', '2006-03-01');

class sss_client {

    private $client;
    private $bucket;

    public function __construct($config) {
        $this->bucket = $config->bucket;
        $this->key = $config->key;
        $this->secret = $config->secret;
        $this->region = $config->region;

    }

    private function initialise() {
        $this->client = S3Client::factory(array(
                'credentials' => array('key' => $config->key, 'secret' => $config->secret),
                'region' => $config->region,
                'version' => AWS_API_VERSION
            ));
    }

    public function push_file($filekey, $filecontent) {

        if (!$client) {
            $this->initialise();
        }

        $result = $this->client->putObject(array(
                'Bucket' => $this->bucket,
                'Key' => $filekey,
                'Body' => $filecontent
            ));

        return $result;
    }
}