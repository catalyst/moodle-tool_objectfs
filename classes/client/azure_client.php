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
 * Azure Blob Storage client.
 *
 * @package    tool_objectfs
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\client;

defined('MOODLE_INTERNAL') || die();

$autoloader = $CFG->dirroot . '/local/azure_storage/vendor/autoload.php';

if (!file_exists($autoloader)) {

    // Stub class with bare implementation for when the SDK prerequisite does not exist.
    class azure_client {
        public function get_availability() {
            return false;
        }

        public function register_stream_wrapper() {
            return false;
        }
    }

    return;
}

require_once($autoloader);

use GuzzleHttp\Exception\ConnectException;
use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Common\Internal\Resources;
use MicrosoftAzure\Storage\Common\ServicesBuilder;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;
use SimpleXMLElement;
use stdClass;
use tool_objectfs\azure\StreamWrapper;

class azure_client implements object_client {

    /** @var BlobRestProxy $client The Blob client. */
    protected $client;

    /** @var string $container The current container. */
    protected $container;

    /**
     * The azure_client constructor.
     *
     * @param $config
     */
    public function __construct($config) {
        $this->container = $config->azure_container;
        $this->set_client($config);
    }

    /**
     * Returns true if the Azure Storage SDK exists and has been loaded.
     *
     * @return bool
     */
    public function get_availability() {
        return true;
    }

    /**
     * Returns the maximum allowed file size that is to be uploaded.
     *
     * @return int
     */
    public function get_maximum_upload_size() {
        return Resources::MAX_BLOCK_BLOB_SIZE;
    }

    /**
     * Configures the BlobRestProxy client for access with the SAS token provided.
     *
     * @param stdClass $config
     */
    public function set_client($config) {
        $accountname = $config->azure_accountname;
        $sastoken = $this->clean_sastoken($config->azure_sastoken);

        // If the account name is specified, append a period to create a valid url.
        // When $accountname is not set, prevent the general exception validation error.
        if ($accountname) {
            $accountname .= '.';
        }

        $sasconnectionstring = "BlobEndpoint=https://" .
            $accountname .
            "blob.core.windows.net;SharedAccessSignature=" .
            $sastoken;

        $sasconnectionstring = str_replace(' ', '', $sasconnectionstring);

        $this->client = ServicesBuilder::getInstance()->createBlobService($sasconnectionstring);
    }

    /**
     * Sets the StreamWrapper to allow accessing the remote content via a blob:// path.
     */
    public function register_stream_wrapper() {
        StreamWrapper::register($this->client);
    }

    public function get_fullpath_from_hash($contenthash) {
        $filepath = $this->get_filepath_from_hash($contenthash);
        return "blob://$this->container/$filepath";
    }

    public function get_seekable_stream_context() {
        $context = stream_context_create(array(
            'blob' => array(
                'seekable' => true
            )
        ));
        return $context;
    }

    /**
     * Trim a leading '?' character from the sas token.
     *
     * @param $sastoken
     * @return bool|string
     */
    private function clean_sastoken($sastoken) {
        if (substr($sastoken, 0, 1) === '?') {
            $sastoken = substr($sastoken, 1);
        }

        return $sastoken;
    }

    private function get_md5_from_hash($contenthash) {
        try {
            $key = $this->get_filepath_from_hash($contenthash);

            $result = $this->client->getBlobProperties($this->container, $key)->getProperties();

        } catch (ServiceException $e) {
            return false;
        }

        $contentmd5 = $result->getContentMD5();

        if ($contentmd5) {
            $md5 = bin2hex(base64_decode($contentmd5));
        } else {
            $md5 = trim($result->getETag(), '"'); // Strip quotation marks.
        }

        return $md5;
    }

    public function verify_object($contenthash, $localpath) {
        $localmd5 = md5_file($localpath);
        $externalmd5 = $this->get_md5_from_hash($contenthash);
        if ($localmd5 === $externalmd5) {
            return true;
        }
        return false;
    }

    protected function get_filepath_from_hash($contenthash) {
        $l1 = $contenthash[0] . $contenthash[1];
        $l2 = $contenthash[2] . $contenthash[3];
        return "$l1/$l2/$contenthash";
    }

    public function test_connection() {
        $connection = new \stdClass();
        $connection->success = true;
        $connection->message = '';

        try {
            $result = $this->client->createBlockBlob($this->container, 'connection_check_file', 'connection_check_file');
            $connection->message = get_string('settings:connectionsuccess', 'tool_objectfs');
        } catch (ServiceException $e) {
            $connection->success = false;
            $details = $this->get_exception_details($e);
            $connection->message = get_string('settings:connectionfailure', 'tool_objectfs') . $details;
        } catch (ConnectException $e) {
            $connection->success = false;
            $details = $e->getMessage();
            $connection->message = get_string('settings:connectionfailure', 'tool_objectfs') . $details;
        }

        return $connection;
    }

    public function test_permissions() {
        $permissions = new \stdClass();
        $permissions->success = true;
        $permissions->messages = array();

        try {
            $result = $this->client->createBlockBlob($this->container, 'permissions_check_file', 'permissions_check_file');
        } catch (ServiceException $e) {
            $details = $this->get_exception_details($e);
            $permissions->messages[] = get_string('settings:writefailure', 'tool_objectfs') . $details;
            $permissions->success = false;
        }

        try {
            $result = $this->client->getBlob($this->container, 'permissions_check_file');
        } catch (ServiceException $e) {
            $errorcode = $this->get_body_error_code($e);

            // Write could have failed.
            if ($errorcode !== 'BlobNotFound') {
                $details = $this->get_exception_details($e);
                $permissions->messages[] = get_string('settings:readfailure', 'tool_objectfs') . $details;
                $permissions->success = false;
            }
        }

        try {
            $result = $this->client->deleteBlob($this->container, 'permissions_check_file');
            $permissions->messages[] = get_string('settings:deletesuccess', 'tool_objectfs');
            $permissions->success = false;
        } catch (ServiceException $e) {
            $errorcode = $this->get_body_error_code($e);

            // Something else went wrong.
            if ($errorcode !== 'AuthorizationPermissionMismatch') {
                $details = $this->get_exception_details($e);
                $permissions->messages[] = get_string('settings:deleteerror', 'tool_objectfs') . $details;
                $permissions->success = false;
            }
        }

        if ($permissions->success) {
            $permissions->messages[] = get_string('settings:permissioncheckpassed', 'tool_objectfs');
        }

        return $permissions;
    }

    protected function get_exception_details(ServiceException $exception) {
        $message = $exception->getErrorMessage();

        if (get_class($exception) !== 'MicrosoftAzure\Storage\Common\Exceptions\ServiceException') {
            return "Not an Azure exception : $message";
        }

        $errorcode = $this->get_body_error_code($exception);

        $details = ' ';

        if ($message) {
            $details .= "ERROR MSG: " . $message . "\n";
        }

        if ($errorcode) {
            $details .= "ERROR CODE: " . $errorcode . "\n";
        }

        return $details;
    }

    /**
     * Moodle form element to display connection details for the Azure service.
     *
     * @param $mform
     * @param $config
     * @return mixed
     */
    public function define_azure_check($mform, $config) {
        global $OUTPUT;

        $client = new azure_client($config);
        $connection = $client->test_connection();

        if ($connection->success) {
            $mform->addElement('html', $OUTPUT->notification($connection->message, 'notifysuccess'));

            // Check permissions if we can connect.
            $permissions = $client->test_permissions();
            if ($permissions->success) {
                $mform->addElement('html', $OUTPUT->notification($permissions->messages[0], 'notifysuccess'));
            } else {
                foreach ($permissions->messages as $message) {
                    $mform->addElement('html', $OUTPUT->notification($message, 'notifyproblem'));
                }
            }

        } else {
            $mform->addElement('html', $OUTPUT->notification($connection->message, 'notifyproblem'));
        }
        return $mform;
    }

    /**
     * Azure settings form with the following elements:
     *
     * Storage account name.
     * Container name.
     * Shared Access Signature.
     *
     * @param $mform
     * @param $config
     * @return mixed
     */
    public function define_client_section($mform, $config) {

        $mform->addElement('header', 'azureheader', get_string('settings:azure:header', 'tool_objectfs'));
        $mform->setExpanded('azureheader');

        $mform = $this->define_azure_check($mform, $config);

        $mform->addElement('text', 'azure_accountname', get_string('settings:azure:accountname', 'tool_objectfs'));
        $mform->addHelpButton('azure_accountname', 'settings:azure:accountname', 'tool_objectfs');
        $mform->setType("azure_accountname", PARAM_TEXT);

        $mform->addElement('text', 'azure_container', get_string('settings:azure:container', 'tool_objectfs'));
        $mform->addHelpButton('azure_container', 'settings:azure:container', 'tool_objectfs');
        $mform->setType("azure_container", PARAM_TEXT);

        $mform->addElement('text', 'azure_sastoken', get_string('settings:azure:sastoken', 'tool_objectfs'));
        $mform->addHelpButton('azure_sastoken', 'settings:azure:sastoken', 'tool_objectfs');
        $mform->setType("azure_sastoken", PARAM_RAW);

        return $mform;
    }

    /**
     * Extract an error code from the XML response.
     *
     * @link https://docs.microsoft.com/en-us/rest/api/storageservices/common-rest-api-error-codes
     * @link https://docs.microsoft.com/en-us/rest/api/storageservices/blob-service-error-codes
     *
     * @param ServiceException $e The exception that contains the XML body.
     * @return string The error code.
     */
    private function get_body_error_code(ServiceException $e) {
        // Casting the stream content to a string will give us the HTTP body content.
        $body = (string) $e->getResponse()->getBody();

        $xml = simplexml_load_string($body);

        if ($xml instanceof SimpleXMLElement) {
            return (string) $xml->Code;
        }

         return '';
    }
}
