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

namespace tool_objectfs\local\store\azure_blob;

use local_azureblobstorage\api;
use Psr\Http\Message\StreamInterface;

/**
 * Azure Blob Storage stream wrapper to use "blob://<container>/<key>" files with PHP.
 *
 * Implementation references,
 * https://github.com/aws/aws-sdk-php/blob/master/src/S3/StreamWrapper.php
 * https://phpazure.codeplex.com/SourceControl/latest#trunk/library/Microsoft/WindowsAzure/Storage/Blob/Stream.php
 *
 * @package    tool_objectfs
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stream_wrapper {

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

    /** @var bool records whether the file was readable when validating the stream_handle */
    private $readable = true;

    /**
     * Register the blob://' stream wrapper
     *
     * @param api $client Client to use with the stream wrapper
     * @param string $protocol Protocol to register as.
     */
    public static function register(api $client, $protocol = 'blob') {
        if (in_array($protocol, stream_get_wrappers())) {
            stream_wrapper_unregister($protocol);
        }

        stream_wrapper_register($protocol, get_called_class(), STREAM_IS_URL);
        $default = stream_context_get_options(stream_context_get_default());
        $default[$protocol]['client'] = $client;
        stream_context_set_default($default);
    }

    /**
     * Stream does not support casting.
     *
     * @param mixed $cast_as
     * @return boolean false
     */
    public function stream_cast($castas): bool {
        return false;
    }

    /**
     * Close the stream
     * @return void
     */
    public function stream_close() {
        $this->body = null;
        $this->hash = null;
    }

    /**
     * Opens the stream
     *
     * @param string $path Path of stream, usually blob://container/hash
     * @param string $mode one of fopen modes, see https://www.php.net/manual/en/function.fopen.php 
     * @param int $options unused
     * @param string $opened_path unused
     *
     * @return bool
     */
    public function stream_open($path, $mode, $options, &$openedpath): bool {
        // Select the protocol from the path, usually blob
        $this->initProtocol($path);

        // TODO this just needs the hash, we already store the container in the client now.
        $this->params = $this->getContainerKey($path);

        // TODO what is this for ?
        $this->mode = rtrim($mode, 'bt');

        // Validate the path for the given mode.
        if ($errors = $this->validate($path, $this->mode)) {
            return $this->triggerError($errors);
        }

        $this->hash = hash_init('md5');

        return $this->boolCall(function() use ($path) {
            switch ($this->mode) {
                case 'r':
return $this->openReadStream();
                case 'a':
return $this->openAppendStream();
                default:
return $this->openWriteStream();
            }
        });
    }

    /**
     * Returns true if nothing more to read (eof i.e. end of file).
     * @return bool
     */
    public function stream_eof(): bool {
        return $this->body->eof();
    }

    /**
     * Flushes (closes) the stream.
     * In our case, this will upload the temporary file to Azure as a blob.
     *
     * @return bool True if successful, else false.
     */
    public function stream_flush(): bool {
        // Cannot flush a read only stream.
        if ($this->mode == 'r') {
            return false;
        }

        // Go to start of the temporarily file stream ($this->body).
        if ($this->body->isSeekable()) {
            $this->body->seek(0);
        }

        // Calculate the final md5 of the file, used for upload integrity checking.
        $hash = hash_final($this->hash);
        $md5 = hex2bin($hash);
        $params = $this->getOptions(true);

        return $this->boolCall(function () use ($params, $md5) {
            $this->getClient()->put_blob($params['Key'], $this->body, $md5);
            return true;
        });
    }

    /**
     * Reads the stream by the given byte amount/count.
     * @param int $count Number of bytes to read
     * @return string
     */
    public function stream_read($count) {
        // If the file isn't readable, we need to return no content. Azure can emit XML here otherwise.
        return $this->readable ? $this->body->read($count) : '';
    }

    /**
     * Go to specific position in stream.
     *
     * @param int $offset
     * @param int $whence
     *
     * @return bool
     */
    public function stream_seek($offset, $whence = SEEK_SET): bool {
        return !$this->body->isSeekable()
            ? false
            : $this->boolCall(function () use ($offset, $whence) {
                $this->body->seek($offset, $whence);
                return true;
            });
    }

    /**
     * Return current position of stream
     * @return int
     */
    public function stream_tell(): int {
        return $this->body->tell();
    }

    /**
     * Write data to stream
     *
     * @param string $data
     * @return int Number of bytes written
     */
    public function stream_write($data): int {
        hash_update($this->hash, $data);
        return $this->body->write($data);
    }

    /**
     * Get information about the current stream
     * @return array
     */
    public function stream_stat(): array {
        $stat = $this->getStatTemplate();
        $stat[7] = $stat['size'] = $this->getSize();
        $stat[2] = $stat['mode'] = $this->mode;

        return $stat;
    }

    /**
     * Get information about a filepath.
     *
     * Provides information for is_dir, is_file, filesize, etc. Works on
     * buckets, keys, and prefixes.
     * @link http://www.php.net/manual/en/streamwrapper.url-stat.php
     *
     * @param string $path
     * @param mixed $flags
     *
     * @return mixed
     */
    public function url_stat($path, $flags) {
        $stat = $this->getStatTemplate();

        try {
            $params = $this->withPath($path);

            // TODO get size and lastmodified from blob properties
            $bp = $this->getclient()->get_blob_properties($params['Key'])->wait();

            // TODO double check right key in $bp.
            $stat['size'] = $stat[7] = $bp['Content-Length'];

            // Set the modification time and last modified to the Last-Modified header.
            // TODO double check right key in $bp.
            $lastmodified = $bp['Last-Modified'];

            $stat['mtime'] = $stat[9] = $lastmodified;
            $stat['ctime'] = $stat[10] = $lastmodified;

            // Regular file with 0777 access - see "man 2 stat".
            $stat['mode'] = $stat[2] = 0100777;

            return $stat;

        // TODO different ex catch
        } catch (ServiceException $ex) {
            // The specified blob does not exist.
            return false;
        }
    }

    /**
     * getContainerKey
     * @param string $path
     *
     * @return array
     */
    private function getcontainerkey($path) {
        // Remove the protocol.
        $parts = explode('://', $path);
        // Get the container, key.
        $parts = explode('/', $parts[1], 2);

        return [
            'Container' => $parts[0],
            'Key'       => isset($parts[1]) ? $parts[1] : null
        ];
    }

    /**
     * Get the stream context options available to the current stream
     *
     * @param bool $removeContextData Set to true to remove contextual kvp's
     *                                like 'client' from the result.
     *
     * @return array
     */
    private function getoptions($removecontextdata = false) {
        // Context is not set when doing things like stat.
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

        if ($removecontextdata) {
            unset($result['client'], $result['seekable']);
        }

        return $result;
    }

    /**
     * Validates the provided stream arguments for fopen and returns an array
     * of errors.
     * @param string $path
     * @param string $mode
     *
     * @return [type]
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

        // When using mode "x" validate if the file exists before attempting to read.
        // 'x' mode is for writing, and the file must exist to write to it.
        if ($mode == 'x' &&
            // TODO implement blob_exists either in this class or in sdk class.
            !$this->getclient()->blob_exists($this->getOption('key'))
        ) {
            $errors[] = "{$path} already exists on Azure Blob Storage";
        }

        // When using mode 'r' we should validate the file exists before opening a handle on it.
        if ($mode == 'r' &&
            // TODO implement blob_exists either in this class or in sdk class.
            !$this->getclient()->blob_exists($this->getOption('key'))
        ) {
            $errors[] = "{$path} does not exist on Azure Blob Storage";
            $this->readable = false;
        }

        return $errors;
    }

    /**
     * Parse the protocol out of the given path.
     *
     * @param string $path
     */
    private function initprotocol($path) {
        $parts = explode('://', $path, 2);
        $this->protocol = $parts[0] ?: 'blob';
    }
}