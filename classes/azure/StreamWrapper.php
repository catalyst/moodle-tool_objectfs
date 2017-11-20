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
 * Azure Blob Storage stream wrapper to use "blob://<container>/<key>" files with PHP.
 *
 * Implementation references,
 * https://github.com/aws/aws-sdk-php/blob/master/src/S3/StreamWrapper.php
 * https://phpazure.codeplex.com/SourceControl/latest#trunk/library/Microsoft/WindowsAzure/Storage/Blob/Stream.php
 *
 * @package    tool_objectfs
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\azure;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/azure_storage/vendor/autoload.php');

use GuzzleHttp\Psr7\Stream;
use GuzzleHttp\Psr7\CachingStream;
use GuzzleHttp\Psr7;
use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Blob\Models\BlobProperties;
use MicrosoftAzure\Storage\Blob\Models\SetBlobPropertiesOptions;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;
use Psr\Http\Message\StreamInterface;

class StreamWrapper {

    /** @var resource|null Stream context (this is set by PHP) */
    public $context;

    /** @var StreamInterface Underlying stream resource */
    private $body;

    /** @var int Size of the body that is opened */
    private $size;

    /** @var array Hash of opened stream parameters */
    private $params = [];

    /** @var string Mode in which the stream was opened */
    private $mode;

    /** @var string The opened protocol (e.g. "blob") */
    private $protocol = 'blob';

    /** @var resource Hash resource that is sent when flushing the file to Azure. */
    private $hash;

    /**
     * Register the blob://' stream wrapper
     *
     * @param BlobRestProxy     $client   Client to use with the stream wrapper
     * @param string            $protocol Protocol to register as.
     */
    public static function register(BlobRestProxy $client, $protocol = 'blob') {
        if (in_array($protocol, stream_get_wrappers())) {
            stream_wrapper_unregister($protocol);
        }

        stream_wrapper_register($protocol, get_called_class(), STREAM_IS_URL);
        $default = stream_context_get_options(stream_context_get_default());
        $default[$protocol]['client'] = $client;
        stream_context_set_default($default);
    }

    public function stream_cast($cast_as) {
        return false;
    }

    public function stream_close() {
        $this->body = null;
        $this->hash = null;
    }

    public function stream_open($path, $mode, $options, &$opened_path) {
        $this->initProtocol($path);
        $this->params = $this->getContainerKey($path);
        $this->mode = rtrim($mode, 'bt');

        if ($errors = $this->validate($path, $this->mode)) {
            return $this->triggerError($errors);
        }

        $this->hash = hash_init('md5');

        return $this->boolCall(function() use ($path) {
            switch ($this->mode) {
                case 'r': return $this->openReadStream();
                case 'a': return $this->openAppendStream();
                default: return $this->openWriteStream();
            }
        });
    }

    public function stream_eof() {
        return $this->body->eof();
    }

    public function stream_flush() {
        if ($this->mode == 'r') {
            return false;
        }

        if ($this->body->isSeekable()) {
            $this->body->seek(0);
        }

        $hash = hash_final($this->hash);
        $md5 = base64_encode(hex2bin($hash));

        $params = $this->getOptions(true);
        $params['Body'] = $this->body;
        $params['ContentMD5'] = $md5;

        return $this->boolCall(function () use ($params) {
            $this->getClient()->createBlockBlob(
                $params['Container'],
                $params['Key'],
                $params['Body']);

            // Set the ContentMD5, as this is not computed server-side for multipart blob uploads.
            $properties = new SetBlobPropertiesOptions(new BlobProperties());
            $properties->setContentMD5($params['ContentMD5']);

            $this->getClient()->setBlobProperties(
                $params['Container'],
                $params['Key'],
                $properties);

            return true;
        });
    }

    public function stream_read($count) {
        return $this->body->read($count);
    }

    public function stream_seek($offset, $whence = SEEK_SET) {
        return !$this->body->isSeekable()
            ? false
            : $this->boolCall(function () use ($offset, $whence) {
                $this->body->seek($offset, $whence);
                return true;
            });
    }

    public function stream_tell() {
        return $this->boolCall(function() { return $this->body->tell(); });
    }

    public function stream_write($data) {
        hash_update($this->hash, $data);
        return $this->body->write($data);
    }

    public function stream_stat() {
        $stat = $this->getStatTemplate();
        $stat[7] = $stat['size'] = $this->getSize();
        $stat[2] = $stat['mode'] = $this->mode;

        return $stat;
    }

    /**
     * Provides information for is_dir, is_file, filesize, etc. Works on
     * buckets, keys, and prefixes.
     * @link http://www.php.net/manual/en/streamwrapper.url-stat.php
     */
    public function url_stat($path, $flags) {
        $stat = $this->getStatTemplate();

        try {
            $params = $this->withPath($path);

            $bp = $this->getClient()->getBlobProperties($params['Container'], $params['Key'])->getProperties();

            $stat['size'] = $stat[7] = $bp->getContentLength();

            // Set the modification time and last modified to the Last-Modified header.
            $lastmodified = $bp->getLastModified()->getTimestamp();

            $stat['mtime'] = $stat[9] = $lastmodified;
            $stat['ctime'] = $stat[10] = $lastmodified;

            // Regular file with 0777 access - see "man 2 stat".
            $stat['mode'] = $stat[2] = 0100777;

            return $stat;

        } catch (ServiceException $ex) {
            // The specified blob does not exist.
            return false;
        }
    }

    /**
     * Parse the protocol out of the given path.
     *
     * @param $path
     */
    private function initProtocol($path) {
        $parts = explode('://', $path, 2);
        $this->protocol = $parts[0] ?: 'blob';
    }

    private function getContainerKey($path) {
        // Remove the protocol
        $parts = explode('://', $path);
        // Get the container, key
        $parts = explode('/', $parts[1], 2);

        return [
            'Container' => $parts[0],
            'Key'       => isset($parts[1]) ? $parts[1] : null
        ];
    }

    /**
     * Validates the provided stream arguments for fopen and returns an array
     * of errors.
     */
    private function validate($path, $mode) {
        $errors = [];

        if (!$this->getOption('Key')) {
            $errors[] = 'Cannot open a bucket. You must specify a path in the '
                . 'form of blob://container/key';
        }

        if (!in_array($mode, ['r', 'w', 'a', 'x'])) {
            $errors[] = "Mode not supported: {$mode}. "
                . "Use one 'r', 'w', 'a', or 'x'.";
        }

        // When using mode "x" validate if the file exists before attempting
        // to read
        if ($mode == 'x' &&
            $this->getClient()->getBlobProperties(
                $this->getOption('Container'),
                $this->getOption('Key')
            )
        ) {
            $errors[] = "{$path} already exists on Azure Blob Storage";
        }

        return $errors;
    }

    /**
     * Get the stream context options available to the current stream
     *
     * @param bool $removeContextData Set to true to remove contextual kvp's
     *                                like 'client' from the result.
     *
     * @return array
     */
    private function getOptions($removeContextData = false) {
        // Context is not set when doing things like stat
        if ($this->context === null) {
            $options = [];
        } else {
            $options = stream_context_get_options($this->context);
            $options = isset($options[$this->protocol])
                ? $options[$this->protocol]
                : [];
        }

        $default = stream_context_get_options(stream_context_get_default());
        $default = isset($default[$this->protocol])
            ? $default[$this->protocol]
            : [];
        $result = $this->params + $options + $default;

        if ($removeContextData) {
            unset($result['client'], $result['seekable']);
        }

        return $result;
    }

    /**
     * Get a specific stream context option
     *
     * @param string $name Name of the option to retrieve
     *
     * @return mixed|null
     */
    private function getOption($name) {
        $options = $this->getOptions();

        return isset($options[$name]) ? $options[$name] : null;
    }

    /**
     * Gets the client.
     *
     * @return BlobRestProxy
     * @throws \RuntimeException if no client has been configured
     */
    private function getClient() {
        if (!$client = $this->getOption('client')) {
            throw new \RuntimeException('No client in stream context');
        }

        return $client;
    }

    /**
     * Get the container and key from the passed path (e.g. blob://container/key)
     *
     * @param string $path Path passed to the stream wrapper
     *
     * @return array Hash of 'Container', 'Key', and custom params from the context
     */
    private function withPath($path) {
        $params = $this->getOptions(true);

        return $this->getContainerKey($path) + $params;
    }

    private function openReadStream() {
        $client = $this->getClient();
        $params = $this->getOptions(true);

        try {
            $blob = $client->getBlob($params['Container'], $params['Key']);
            $this->body = Psr7\stream_for($blob->getContentStream());
        } catch (ServiceException $e) {
            // Prevent the client from keeping the request open when the content cannot be found.
            $response = $e->getResponse();
            $this->body = $response->getBody();
        }

        // Wrap the body in a caching entity body if seeking is allowed
        if ($this->getOption('seekable') && !$this->body->isSeekable()) {
            $this->body = new CachingStream($this->body);
        }

        return true;
    }

    private function openWriteStream() {
        $this->body = new Stream(fopen('php://temp', 'r+'));
        return true;
    }

    private function openAppendStream() {
        try {
            // Get the body of the object and seek to the end of the stream
            $client = $this->getClient();
            $params = $this->getOptions(true);
            $this->body = $client->getBlob($params['Container'], $params['Key']);
            $this->body->seek(0, SEEK_END);
            return true;
        } catch (ServiceException $e) {
            // The object does not exist, so use a simple write stream
            return $this->openWriteStream();
        }
    }

    /**
     * Gets a URL stat template with default values
     *
     * @return array
     */
    private function getStatTemplate() {
        return [
            0  => 0,  'dev'     => 0,
            1  => 0,  'ino'     => 0,
            2  => 0,  'mode'    => 0,
            3  => 0,  'nlink'   => 0,
            4  => 0,  'uid'     => 0,
            5  => 0,  'gid'     => 0,
            6  => -1, 'rdev'    => -1,
            7  => 0,  'size'    => 0,
            8  => 0,  'atime'   => 0,
            9  => 0,  'mtime'   => 0,
            10 => 0,  'ctime'   => 0,
            11 => -1, 'blksize' => -1,
            12 => -1, 'blocks'  => -1,
        ];
    }

    /**
     * Invokes a callable and triggers an error if an exception occurs while
     * calling the function.
     *
     * @param callable $fn
     * @param int      $flags
     *
     * @return bool
     */
    private function boolCall(callable $fn, $flags = null) {
        try {
            return $fn();
        } catch (\Exception $e) {
            return $this->triggerError($e->getMessage(), $flags);
        }
    }

    /**
     * Trigger one or more errors
     *
     * @param string|array $errors Errors to trigger
     * @param mixed        $flags  If set to STREAM_URL_STAT_QUIET, then no
     *                             error or exception occurs
     *
     * @return bool Returns false
     * @throws \RuntimeException if throw_errors is true
     */
    private function triggerError($errors, $flags = null) {
        // This is triggered with things like file_exists()
        if ($flags & STREAM_URL_STAT_QUIET) {
            return $flags & STREAM_URL_STAT_LINK
                // This is triggered for things like is_link()
                ? $this->getStatTemplate()
                : false;
        }

        // This is triggered when doing things like lstat() or stat()
        trigger_error(implode("\n", (array) $errors), E_USER_WARNING);

        return false;
    }

    /**
     * Returns the size of the opened object body.
     *
     * @return int|null
     */
    private function getSize() {
        $size = $this->body->getSize();

        return $size !== null ? $size : $this->size;
    }

}
