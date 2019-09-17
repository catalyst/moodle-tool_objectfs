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
 * Description of  tool_objectfs
 *
 * @package    tool_objectfs
 * @copyright  2019 Matt Clarkson <mattc@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\local\store\swift;
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/openstack/vendor/autoload.php');

use GuzzleHttp\Psr7\Stream;

/**
 * Stream wrapper for swift.
 */
class stream_wrapper {

    /**
     * PHP stream context
     *
     * @var resource
     */
    public $context;

    /**
     *  Binary file flag
     *
     * @var boolean
     */
    private $isbinary = false;

    /**
     * Text file flag
     *
     * @var boolean
     */
    private $istext = true;

    /**
     * File opened for writing
     *
     * @var boolean
     */
    private $iswriting = false;

    /**
     * File opened for reading
     *
     * @var boolean
     */
    private $isreading = false;

    /**
     * File created if not present
     *
     * @var boolean
     */
    private $createifnotfound = true;

    /**
     * File to be truncated
     *
     * @var boolean
     */
    private $istruncating = false;

    /**
     * File to be appended
     *
     * @var boolean
     */
    private $isappending = false;

    /**
     * File can't be overwritten
     *
     * @var boolean
     */
    private $nooverwrite = false;

    /**
     * Openstack auth token
     *
     * @var authtoken
     */
    private static $authtoken;

    /**
     * Stream context in array form
     *
     * @var array
     */
    private $contextarray;

    /**
     * Openstack object
     *
     * @var object
     */
    private $object;

    /**
     * Guzzle stream interface
     *
     * @var object
     */
    private $objstream;

    /**
     * Object name
     *
     * @var string
     */
    private $objectname;

    /**
     * Openstack container
     *
     * @var object
     */
    private $container;

    /**
     * Directory listing
     *
     * @var array
     */
    private $dirlisting = [];

    /**
     * Location of directory seek
     *
     * @var integer
     */
    private $dirindex = 0;

    /**
     * Directory prefix
     *
     * @var string
     */
    private $dirprefix = '';

    /**
     * File needs to be uploaded to object store
     *
     * @var bool
     */
    private $isdirty;

    /**
     * Context for calls that don't allow a context to be supplied. e.g. stat()
     *
     * @var resource
     */
    private static $defaultcontext;


    //
    // Stream API functions
    //

    /**
     * Close directory
     *
     * @return boolean
     */
    public function dir_closedir() {
        $this->dirindex = 0;
        $this->dirlisting = [];
        return true;
    }

    /**
     * Open a directory
     *
     * @param string $path
     * @param integer $options
     * @return boolean
     */
    public function dir_opendir($path, $options) {
        $url = $this->parse_url($path);

        if (empty($url['host'])) {
            trigger_error('Container name is required.' , E_USER_WARNING);
            return false;
        }

        $container = $this->get_container($url['host']);

        if (empty($url['path'])) {
            $this->dirprefix = '';
        } else {
            $this->dirprefix = $url['path'];
        }

        foreach ($container->listObjects(['path' => $this->dirprefix]) as $object) {
            $this->dirlisting[] = $object;
        }

        return true;
    }

    /**
     * Read item from directory
     *
     * @return mixed
     */
    public function dir_readdir() {
        if (count($this->dirlisting) <= $this->dirindex) {
            return false;
        }

        $item = $this->dirlisting[$this->dirindex];

        $this->dirindex++;

        return $item->name;
    }

    public function stream_close() {
        return $this->push_object();
    }

    public function stream_eof() {
        return $this->objstream->eof();
    }


    /**
     * Open a stream
     *
     * @param string $path
     * @param string $mode
     * @param integer $options
     * @param string $openedpath
     * @return boolean
     */
    public function stream_open($path, $mode, $options, &$openedpath = null) {

        $this->set_mode($mode);

        $url = $this->parse_url($path);

        if (empty($url['host'])) {
            trigger_error('No container name was supplied in ' . $path, E_USER_WARNING);
            return false;
        }

        if (empty($url['path'])) {
            trigger_error('No object name was supplied in ' . $path, E_USER_WARNING);
            return false;
        }

        $containername = $url['host'];
        $this->objectname = $url['path'];

        if (!$this->container = $this->get_container($containername)) {
            trigger_error('Container not found: '.$containername, E_USER_WARNING);
        }

        $this->object = $this->container->getObject($this->objectname);

        try {
            $this->object->retrieve();

            if (empty($this->object->lastModified)) { // File exits even though retrieve worked.

                $this->objstream = new Stream(fopen('php://temp', $mode));
            } else {
                if ($this->iswriting && $this->nooverwrite) {
                    trigger_error('File exists and cannot be overwritten', E_USER_WARNING);
                    return false;
                }

                if ($this->isappending || $this->isreading) {

                    $this->objstream = $this->object->download();

                } else {
                    $this->objstream = new Stream(fopen('php://temp', $mode));
                }

                if ($this->isappending) {
                    $this->objstream->seek(0, SEEK_END);
                }
            }

        } catch (\OpenStack\Common\Error\BadResponseError $e) {

            if (!$this->iswriting) {
                trigger_error('Files does not exist', E_USER_WARNING);
                return false;
            }

            $this->objstream = new Stream(fopen('php://temp', $mode));

        } catch (\Exeption $e) {
            trigger_error('Failed to fetch object: ' . $e->getMessage(), E_USER_WARNING);

            return false;
        }

        return true;
    }


    /**
     * Read from open stream
     *
     * @param integer $count
     * @return string
     */
    public function stream_read($count) {

        return $this->objstream->read($count);
    }

    /**
     * Set position of file pointer
     *
     * @param integer $offset
     * @param integer $whence
     * @return boolean
     */
    public function stream_seek($offset, $whence = SEEK_SET) {

        $this->objstream->seek($offset, $whence);
        return true;
    }


    /**
     * Stat open file
     *
     * @return array
     */
    public function stream_stat() {

        $size = $this->objstream->getSize();

        return $this->generate_stat($this->object, $this->container, $size);
    }


    /**
     * Get position of file pointer
     *
     * @return integer
     */
    public function stream_tell() {

        return $this->objstream->tell();
    }


    /**
     * Write to local buffer
     *
     * @param string $data
     * @return integer
     */
    public function stream_write($data) {

        if (!$this->iswriting) {
            tigger_error("Steam is not writable", E_USER_WARNING);
        }

        $this->isdirty = true;
        return $this->objstream->write($data);
    }

    /**
     * Upload buffer to object storage
     *
     * @return boolean
     */
    public function steam_flush() {

        return $this->push_object();
    }

    /**
     * Remove object
     *
     * @param string $path
     * @return boolean
     */
    public function unlink($path) {

        $url = $this->parse_url($path);

        try {

            $this->get_container($url['host'])->getObject($url['path'])->delete();
            return true;

        } catch (\OpenStack\Common\Error\BadResponseError $e) {

            // Openstack will return a 404 if the object was not fully replicated. Just assume a 404 means the delete worked.
            if ($e->getResponse()->getStatusCode() == 404) {
                return true;
            }

            trigger_error("Unable to unlink file", E_USER_WARNING);
            return false;

        } catch (\Exception $e) {
            trigger_error("Unable to unlink file", E_USER_WARNING);
            return false;

        }
    }

    /**
     * Stat file based on path
     *
     * @param string $path
     * @param integer $flags
     * @return void
     */
    public function url_stat($path, $flags) {

        $url = $this->parse_url($path);

        if (empty($url['host']) || empty($url['path'])) {

            if ($flags & STREAM_URL_STAT_QUIET) {
                trigger_error('Container name (host) and path are required.', E_USER_WARNING);
            }
            return true;
        }

        $container = $this->get_container($url['host']);

        $object = $container->getObject($url['path']);

        if ($flags & STREAM_URL_STAT_QUIET) {

            try {
                $stream = $object->download();
                return $this->generate_stat($object, $container, $stream->getSize());
            } catch (\Exception $e) {
                return false;
            }
        }

        $stream = $object->download();
        return $this->generate_stat($object, $container, $stream->getSize());
    }


    //
    // Non-stream API functions
    //

    /**
     * Set context for functions that don't accept a context. e.g. stat()
     *
     * @param resource $context
     * @return void
     */
    public static function set_default_context($context) {
        self::$defaultcontext = $context;
    }

    //
    // Internal functions.
    //

    /**
     * Parse URL to object
     *
     * @param string $url
     * @return array
     */
    private function parse_url($url) {
        $res = parse_url($url);

        // These have to be decode because they will later
        // be encoded.
        foreach ($res as $key => $val) {
            if ($key == 'host') {
                $res[$key] = urldecode($val);
            } else if ($key == 'path') {
                if (strpos($val, '/') === 0) {
                    $val = substr($val, 1);
                }
                $res[$key] = urldecode($val);

            }
        }
        return $res;
    }


    /**
     * Get item from context
     *
     * @param string $key
     * @return string
     */
    private function context($key) {

        $defaultctx = [];
        $ctx = [];

        if (is_resource(self::$defaultcontext)) {
            $defaultctx = stream_context_get_options(self::$defaultcontext);
        }

        if (empty($this->contextarray)) {
            if (is_resource($this->context)) {
                $ctx = stream_context_get_options($this->context);
            }

            if (isset($ctx['swift'])) {
                $this->contextarray = $ctx['swift'];
            } else if (isset($defaultctx['swift'])) {

                $this->contextarray = $defaultctx['swift'];
            } else {
                trigger_error("context not supplied", E_USER_WARNING);
                return false;
            }
        }
        if (!empty($this->contextarray[$key])) {
            return $this->contextarray[$key];
        } else {
            return '';
        }
    }


    /**
     * Upload object to object store
     *
     * @return boolean
     */
    private function push_object() {
        if (!$this->isdirty) {
            return true;
        }

        try {
            $this->object = $this->container->createObject([
                'name' => $this->objectname,
                'stream' => $this->objstream,
            ]);

            return true;
        } catch (\Exception $e) {
            trigger_error("Object creation failed", E_USER_WARNING);
            return false;
        }

    }


    /**
     * Get container object
     *
     * @param string $containername
     * @return object
     */
    private function get_container($containername) {
        $params = [
            'authUrl' => $this->context('endpoint'),
            'region'  => $this->context('region'),
            'user'    => [
                'name' => $this->context('username'),
                'password' => $this->context('password'),
                'domain' => ['id' => 'default'],
            ],
            'scope'   => ['project' => ['id' => $this->context('projectid')]]
        ];

        if (!isset($this->token['expires_at']) || (new \DateTimeImmutable($this->token['expires_at'])) < (new \DateTimeImmutable('now'))) {

            $openstack = new \OpenStack\OpenStack($params);
            try {
                $this->token = $openstack->identityV3()->generateToken($params)->export();
            } catch (\Exception $e) {
                trigger_error("Openstack auth error", E_USER_WARNING);
            }

        } else {
            $params['cachedToken'] = $this->token;
            $openstack = new \OpenStack\OpenStack($params);
        }

        return $openstack->objectStoreV1()->getContainer($containername);
    }


    /**
     * Set file node flags
     *
     * @param string $mode
     * @return void
     */
    private function set_mode($mode) {
        $mode = strtolower($mode);

        $this->isbinary = strpos($mode, 'b') !== false;
        $this->istext = strpos($mode, 't') !== false;

        // rewrite mode to remove b or t:
        $mode = preg_replace('/[bt]?/', '', $mode);

        switch ($mode) {
            case 'r+':
                $this->iswriting = true;
            case 'r':
                $this->isreading = true;
                $this->createifnotfound = false;
                break;
            case 'w+':
                $this->isreading = true;
            case 'w':
                $this->istruncating = true;
                $this->iswriting = true;
                break;
            case 'a+':
                $this->isreading = true;
            case 'a':
                $this->isappending = true;
                $this->iswriting = true;
                break;
            case 'x+':
                $this->isreading = true;
            case 'x':
                $this->iswriting = true;
                $this->nooverwrite = true;
                break;
            case 'c+':
                $this->isreading = true;
            case 'c':
                $this->iswriting = true;
                break;
            default:
                $this->isreading = true;
                $this->iswriting = true;
                break;
        }
    }


    /**
     * Generate a file stat
     *
     * @param object $object
     * @param object $container
     * @param int $size
     * @return array
     */
    private function generate_stat($object, $container, $size) {

        $mode = octdec(100770);

        // We have to fake the UID value in order for is_readible()/is_writable()
        // to work. Note that on Windows systems, stat does not look for a UID.
        if (function_exists('posix_geteuid')) {
            $uid = posix_geteuid();
            $gid = posix_getegid();
        } else {
            $uid = 0;
            $gid = 0;
        }

        $modtime = !empty($object->lastModified) ? $object->lastModified : 0;

        $values = array(
            'dev' => 0,
            'ino' => 0,
            'mode' => $mode,
            'nlink' => 0,
            'uid' => $uid,
            'gid' => $gid,
            'rdev' => 0,
            'size' => $size,
            'atime' => $modtime,
            'mtime' => $modtime,
            'ctime' => $modtime,
            'blksize' => -1,
            'blocks' => -1,
        );

        $final = array_values($values) + $values;

        return $final;
    }

}