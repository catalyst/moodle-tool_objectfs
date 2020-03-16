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

namespace tool_objectfs\local\store;

use Aws\CloudFront\CloudFrontClient;

defined('MOODLE_INTERNAL') || die();

class cf_client {

    /** @var CloudFrontClient $client */
    private $client;

    private $resourcedomain;

    private $keypairid;

    private $pemfile;

    public function __construct($config) {
        $this->set_client($config);
        $this->resourcedomain = $config->cloudfrontresourcedomain;
        $this->expirationtime = $config->expirationtime;
        $this->keypairid = $config->cloudfrontkeypairid;
        $this->pemfile = $config->cloudfrontprivatekeypemfilepathname;
    }

    public function set_client($config) {
        $this->client = new CloudFrontClient([
            'profile' => 'default',
            'version' => 'latest', /* Latest: 2019-03-26 | AWS_API_VERSION */
            'region' => $config->s3_region,  /* The region is the source bucket region ? - 'ap-southeast-2' */
        ]);
    }

    protected function get_filepath_from_hash($contenthash) {
        $l1 = $contenthash[0] . $contenthash[1];
        $l2 = $contenthash[2] . $contenthash[3];
        return "$l1/$l2/$contenthash";
    }

    public function generate_presigned_url($contenthash, $headers = []) {
        $key = $this->get_filepath_from_hash($contenthash);

        $expires = 0;
        if (empty($this->expirationtime)) {
            $expires = time() + $this->expirationtime; // Example: time()+300.
        }

        $resourcekey = $this->resourcedomain . '/' . $key;

        $signingparameters = [
            'url' => $resourcekey,
            'expires' => $expires,
            'key_pair_id' => $this->keypairid,
            'private_key' => realpath($this->pemfile),
        ];
        $signedurlcannedpolicy = $this->client->getSignedUrl($signingparameters);
        $signedurl = (string)$signedurlcannedpolicy;

        /* $headers[] = 'Location:"' . $signedurl . '"'; // This may cause loss of headers (etag for example). */
        return $signedurl;
    }
}
