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

require_once(__DIR__ . '/../sdk/aws-autoloader.php');

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

defined('MOODLE_INTERNAL') || die();

define('AWS_API_VERSION', '2006-03-01');

class sss_client {

    private $client;
    private $bucket;

    public function __construct($config) {
        $this->bucket = $config->bucket;
        $this->client = S3Client::factory(array(
                'credentials' => array('key' => $config->key, 'secret' => $config->secret),
                'region' => $config->region,
                'version' => AWS_API_VERSION
        ));
        $this->client->registerStreamWrapper();
    }

    public function push_file($filekey, $filecontent) {
        try {
            $result = $this->client->putObject(array(
                        'Bucket' => $this->bucket,
                        'Key' => $filekey,
                        'Body' => $filecontent
                    ));
            return $result;
        } catch (S3Exception $e) {
            mtrace($e);
            return false;
        }
    }

    // Checks file is in s3 and its size matches expeted.
    public function check_file($filekey, $expectedsize) {
        try {
            $result = $this->client->headObject(array(
                        'Bucket' => $this->bucket,
                        'Key' => $filekey,
                ));

            if ($result['ContentLength'] == $expectedsize) {
                return true;
            }

        } catch (S3Exception $e) {
            mtrace($e);
        }
        return false;
    }

    // Returns s3 fullpath to use with php file functions.
    public function get_fullpath_from_contenthash($contenthash) {
        return "s3://{$this->bucket}/{$contenthash}";
    }

    public function test_connection() {
        try {
            // There is no check connection in the AWS API.
            // We use list buckets instead and check the bucket is in the list.
            $result = $this->client->listBuckets();
            $buckets = $result['Buckets'];

            foreach ($buckets as $bucket) {
                if ($bucket['Name'] == $this->bucket) {
                    return true;
                }
            }

        } catch (S3Exception $e) {
            mtrace($e);
        }

        return false;
    }
}