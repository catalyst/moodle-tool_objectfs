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

namespace tool_objectfs\check;

use action_link;
use core\check\check;
use core\check\result;
use moodle_url;
use stored_file;
use tool_objectfs\local\manager;

/**
 * Test that requested pre-signed URLs respond with the expected file headers.
 *
 * @package    tool_objectfs
 * @author     Andrew Madden <andrewmadden@catalyst-au.net>
 * @copyright  2022 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class presigned_urls extends check {

    /**
     * A link to a place to action this
     *
     * @return action_link
     */
    public function get_action_link(): ?\action_link {
        return new \action_link(
            new \moodle_url('/admin/settings.php?section=tool_objectfs_settings'),
            get_string('pluginsettings', 'tool_objectfs'));
    }

    /**
     * Test that presigned urls are set up correctly.
     *
     * @return result An object with information about the test.
     *
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \file_exception
     * @throws \moodle_exception
     * @throws \stored_file_creation_exception
     */
    public function get_result(): result {
        $config = manager::get_objectfs_config();
        if (empty($config->filesystem)) {
            return new result(result::INFO, get_string('check:presigned_urls:infofilesystem', 'tool_objectfs'));
        }

        $fs = new $config->filesystem();
        if (!$fs->supports_presigned_urls()) {
            return new result(result::INFO, get_string('check:presigned_urls:infofilesystempresigned', 'tool_objectfs'));
        }

        $testfiles = self::load_files($fs);
        $requests = $this->generate_requests($testfiles);

        // Use first file to check that presigned urls will be used.
        if (!$fs->should_redirect_to_presigned_url($testfiles[0]->get_contenthash(), $testfiles[0])) {
            return new result(result::INFO, get_string('check:presigned_urls:infopresignedsetup', 'tool_objectfs'));
        }

        // Make request to Moodle server to get the files.
        $headers = $this->fetch_headers($requests);
        $presignedrequests = [];
        foreach ($testfiles as $testfile) {
            $header = array_shift($headers);
            ['httpcodes' => $httpcodes, 'headerparts' => $headerparts] = $this->decode_header($header);

            // Check that we were redirected to a presigned url.
            if (!in_array(303, $httpcodes) || !array_key_exists('Location', $headerparts)) {
                return new result(result::ERROR,
                    get_string('check:presigned_urls:errorredirect', 'tool_objectfs', ['filename' => $testfile->get_filename()]));
            }

            // Now use the generated presigned urls to make the requests for the files.
            // It's safer to recreate the presigned url using Moodle API.
            $presignedurl = new moodle_url($headerparts['Location']);
            $presignedrequests[] = [
                'url' => $presignedurl->out(false),
                'returntransfer' => true,
            ];

            // Check if filename is as expected. We need to utf8_encode the filename as that is how it is sent in request.
            $disposition = $presignedurl->get_param('response-content-disposition');
            if (is_null($disposition) || !str_contains($disposition, utf8_encode($testfile->get_filename()))) {
                return new result(result::ERROR,
                    get_string('check:presigned_urls:errorfilename', 'tool_objectfs', ['filename' => $testfile->get_filename()]));
            }
        }

        // Now we can fetch the actual files.
        $headers = $this->fetch_headers($presignedrequests);
        foreach ($testfiles as $testfile) {
            $header = array_shift($headers);
            ['httpcodes' => $httpcodes, 'headerparts' => $headerparts] = $this->decode_header($header);

            // Check that we successfully retrieved file.
            if (!in_array(200, $httpcodes)) {
                return new result(result::ERROR,
                    get_string('check:presigned_urls:errorpresignedfetch', 'tool_objectfs', ['filename' => $testfile->get_filename()]));
            }

            // Check if content length is as expected.
            if (!array_key_exists('content-length', $headerparts) || $headerparts['content-length'] != $testfile->get_filesize()) {
                return new result(result::ERROR,
                        get_string('check:presigned_urls:errorfilesize', 'tool_objectfs', ['filename' => $testfile->get_filename()]));
            }
        }
        return new result(result::OK, get_string('check:presigned_urls:success', 'tool_objectfs'));
    }

    /**
     * Get array of files created from fixtures.
     *
     * @param \file_system $fs Current file system.
     * @return stored_file[] Array of files to test.
     *
     * @throws \dml_exception
     * @throws \file_exception
     * @throws \stored_file_creation_exception
     */
    public static function load_files(\file_system $fs): array {
        global $CFG;
        $filestorage = get_file_storage();
        $fixturespath = $CFG->dirroot.'/admin/tool/objectfs/tests/fixtures/';
        $fixturesfiles = glob($fixturespath.'*');
        $syscontext = \context_system::instance();
        $testfiles = [];

        foreach ($fixturesfiles as $fixturesfile) {
            // Filter out possible compressed files.
            if (false !== strpos($fixturesfile, '.br')) {
                continue;
            }
            $testfilename = str_replace($fixturespath, '', $fixturesfile);

            $contextid = $syscontext->id;
            $component = 'tool_objectfs';
            $filearea = 'settings';
            $itemid = 0;
            $filepath = '/';

            $filerecord = array(
                'contextid' => $contextid,
                'component' => $component,
                'filearea'  => $filearea,
                'itemid'    => $itemid,
                'filepath'  => $filepath,
                'filename'  => $testfilename
            );

            $testfile = $filestorage->get_file($contextid, $component, $filearea, $itemid, $filepath, $testfilename);
            if (!$testfile) {
                $testfile = $filestorage->create_file_from_pathname($filerecord, $fixturesfile);
            }

            $contenthash = $testfile->get_contenthash();
            $readable = $fs->is_file_readable_externally_by_hash($contenthash);
            if (!$readable) {
                $fs->copy_from_local_to_external($contenthash);
            }
            $testfiles[] = $testfile;
        }

        return $testfiles;
    }


    /**
     * Generate a file url with adding a param to set 'Expires' header.
     *
     * @param stored_file $file A moodle file.
     * @param int|bool $expires Seconds until the presigned url expires.
     * @param bool $forcedownload Whether file should be downloaded.
     * @return string A url to fetch a file.
     */
    private function generate_file_url(stored_file $file, $expires = false, bool $forcedownload = false): string {
        $url = \moodle_url::make_pluginfile_url(
            \context_system::instance()->id,
            OBJECTFS_PLUGIN_NAME,
            'settings',
            0,
            '/',
            $file->get_filename(),
            $forcedownload
        );
        $expires = (-1 !== $expires) ? $expires : false;
        if (false !== $expires) {
            $url->param('expires', $expires);
        }
        return $url->out();
    }

    /**
     * Pull out the http codes, and the header key pairs from a header string.
     *
     * @param string $header A header string response.
     * @return array list($httpcodes, $headerparts) httpcodes contains list of HTTP codes in header, and header parts contains
     * list of key-value pairs decoded from the header.
     */
    private function decode_header(string $header): array {
        $headerparts = [];
        $httpcodes = [];

        $responseparts = explode("\r\n", $header);
        foreach ($responseparts as $part) {
            if (empty($part)) {
                continue;
            }

            // First see if it's a HTTP response code.
            if (strpos($part, "HTTP") !== false) {
                $httpparts = explode (" ", $part);
                $httpcodes[] = $httpparts[1];
            } else {
                // Otherwise, create a header map.
                $headerpair = explode(":", $part, 2);
                $headerparts[$headerpair[0]] = trim($headerpair[1]);
            }
        }
        return ['httpcodes' => $httpcodes, 'headerparts' => $headerparts];
    }

    /**
     * Fetch headers for a list of curl requests.
     *
     * @param array $requests Array of curl request options.
     * @return array Array of header strings for each request provided.
     *
     * @throws \coding_exception
     */
    private function fetch_headers(array $requests): array {
        $curl = new \curl();
        $curl->setopt([
            'CURLOPT_HEADER' => true,
            'CURLOPT_NOBODY' => true,
        ]);
        return $curl->download($requests);
    }

    /**
     * Generate a list of curl request options to fetch multiple Moodle files.
     *
     * @param array $files List of stored_file objects.
     * @return array List of curl request options.
     */
    private function generate_requests(array $files): array {
        $requests = [];
        foreach ($files as $file) {
            $requests[] = [
                'url' => $this->generate_file_url($file),
                'returntransfer' => true,
            ];
        }
        return $requests;
    }
}
