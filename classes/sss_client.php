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

    /**
     * Initialises s3 client to use.
     *
     * @param object $config sssfs config
     */
    public function __construct($config) {
        $this->bucket = $config->bucket;
        $this->client = S3Client::factory(array(
                'credentials' => array('key' => $config->key, 'secret' => $config->secret),
                'region' => $config->region,
                'version' => AWS_API_VERSION
        ));

        // Registers 's3://bucket' as a prefix for file actions.
        $this->client->registerStreamWrapper();
    }

    /**
     * Checks file is in s3 and its size matches expeted.
     * We could hash the contents and compare, but we
     * do this to keep executions speed low.
     *
     * @param  string $filekey contenthash used as key in s3.
     * @param  int $expectedsize expected size of the file.
     * @return boolean true on success, false on failure
     * @throws S3Exceptions.
     */
    public function check_file($filekey, $expectedmd5) {
        $result = $this->client->headObject(array(
                        'Bucket' => $this->bucket,
                        'Key' => $filekey));
        $awsmd5 = trim($result['ETag'], '"'); // Strip quotation marks.

        if ($awsmd5 == $expectedmd5) {
            return true;
        }

        return false;
    }

    /**
     * Returns s3 fullpath to use with php file functions.
     *
     * @param  string $contenthash contenthash used as key in s3.
     * @return string fullpath to s3 object.
     */
    public function get_sss_fullpath_from_hash($contenthash) {
        $l1 = $contenthash[0] . $contenthash[1];
        $l2 = $contenthash[2] . $contenthash[3];
        return "s3://$this->bucket/$l1/$l2/$contenthash";
    }

    public function path_is_local($path) {
        $sssprefix = 's3://';
        $pathprefix = substr($path, 0, 5);
        if ($sssprefix === $pathprefix) {
            return false;
        }
        return true;
    }

    /**
     * Tests connection to S3 and bucket.
     * There is no check connection in the AWS API.
     * We use list buckets instead and check the bucket is in the list.
     *
     * @return boolean true on success, false on failure.
     */
    public function test_connection() {
        try {
            $result = $this->client->headBucket(array(
                            'Bucket' => $this->bucket));
            return true;
        } catch (S3Exception $e) {
            return false;
        }
    }
}
