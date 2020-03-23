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
 * Objectfs config class.
 *
 * @package   tool_objectfs
 * @author    Gleimer Mora <gleimermora@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\config;

use dml_exception;
use ReflectionClass;
use ReflectionException;
use stdClass;

defined('MOODLE_INTERNAL') || die;

/**
 * Class config
 * @package tool_objectfs
 * @param int enabletasks 0
 * @param int enablelogging 0
 * @param int sizethreshold 10240
 * @param int minimumage 604800
 * @param int deletelocal 0
 * @param int consistencydelay 600
 * @param int maxtaskruntime 60
 * @param int logging 0
 * @param int preferexternal 0
 * @param int batchsize 10000
 * @param string filesystem ''
 * @param int enablepresignedurls 0
 * @param int expirationtime 600
 * @param int presignedminfilesize 0
 * @param string s3_key ''
 * @param string s3_secret ''
 * @param string s3_bucket ''
 * @param string s3_region 'us-east-1'
 * @param string do_key ''
 * @param string do_secret ''
 * @param string do_space ''
 * @param string do_region 'sfo2'
 * @param string azure_accountname ''
 * @param string azure_container ''
 * @param string azure_sastoken ''
 * @param string openstack_authurl ''
 * @param string openstack_region ''
 * @param string openstack_container ''
 * @param string openstack_username ''
 * @param string openstack_password ''
 * @param string openstack_tenantname ''
 * @param string openstack_projectid ''
 */
class config extends singleton {

    /** @var stdClass $config */
    protected $config;

    /** @var array $typemap */
    private $typemap = [];

    /**
     * config constructor.
     */
    protected function __construct() {
        parent::__construct();
        $this->config = new stdClass();
        $this->set_settings();
    }

    /**
     * @param string $name
     * @return bool
     */
    public function get($name) {
        if (property_exists($this->config, $name) !== false) {
            settype( $this->config->$name, $this->typemap[$name]);
            return $this->config->$name;
        }
        return false;
    }

    /**
     * @throws ReflectionException
     * @throws dml_exception
     */
    private function set_settings() {
        $ref = new ReflectionClass(self::class);
        $doc = $ref->getDocComment();
        $pattern = "#(@param+\s*[a-zA-Z0-9, ()_].*)#";
        preg_match_all($pattern, $doc, $matches, PREG_PATTERN_ORDER);
        foreach ($matches[0] as $match) {
            list(, $type, $name, $value) = explode(' ', $match);
            settype($value, $type);
            $this->typemap[$name] = $type;
            $this->config->$name = $value;
        }
        $storedconfig = get_config('tool_objectfs');

        // Override defaults if set.
        foreach ($storedconfig as $key => $value) {
            $this->config->$key = $value;
        }
    }
}
