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

namespace tool_objectfs\tests;

use dml_exception;
use moodle_exception;
use stdClass;
use stored_file;
use tool_objectfs\local\manager;
use tool_objectfs\local\object_manipulator\candidates\candidates_finder;
use tool_objectfs\local\store\object_file_system;
use tool_objectfs\local\store\signed_url;

/**
 * Testcase with useful / shared methods for common objectfs tests.
 *
 * @package   tool_objectfs
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class testcase extends \advanced_testcase {
    /** @var test_file_system Filesystem */
    public $filesystem;

    /** @var \tool_objectfs\log\objectfs_logger Logger */
    public $logger;

    /**
     * setUp
     * @return void
     */
    protected function setUp(): void {
        global $CFG;
        $CFG->alternative_file_system_class = '\\tool_objectfs\\tests\\test_file_system';
        $CFG->forced_plugin_settings['tool_objectfs']['deleteexternal'] = false;
        $CFG->objectfs_environment_name = 'test';
        $this->filesystem = new test_file_system();
        $this->logger = new \tool_objectfs\log\null_logger();

        $this->resetAfterTest(true);
    }

    /**
     * reset_file_system
     * @return void
     */
    protected function reset_file_system() {
        $this->filesystem = new test_file_system();
    }

    /**
     * create_local_file_from_path
     * @param string $pathname
     *
     * @return stored_file
     */
    protected function create_local_file_from_path($pathname) {
        $fs = get_file_storage();
        $syscontext = \context_system::instance();
        $component = 'core';
        $filearea  = 'unittest';
        $itemid    = 0;
        $filepath  = '/';
        $sourcefield = 'Copyright stuff';
        $filerecord = [
            'contextid' => $syscontext->id,
            'component' => $component,
            'filearea'  => $filearea,
            'itemid'    => $itemid,
            'filepath'  => $filepath,
            'filename'  => $pathname,
            'source'    => $sourcefield,
            'mimetype'  => 'text',
        ];
        $file = $fs->create_file_from_pathname($filerecord, $pathname);

        manager::update_object_by_hash($file->get_contenthash(), OBJECT_LOCATION_LOCAL);
        return $file;
    }

    /**
     * create_local_file
     * @param string $content
     *
     * @return stored_file
     */
    protected function create_local_file($content = 'test content') {
        $fs = get_file_storage();
        $syscontext = \context_system::instance();
        $component = 'core';
        $filearea  = 'unittest';
        $itemid    = 0;
        $filepath  = '/';
        $sourcefield = 'Copyright stuff';
        $filerecord = [
            'contextid' => $syscontext->id,
            'component' => $component,
            'filearea'  => $filearea,
            'itemid'    => $itemid,
            'filepath'  => $filepath,
            'filename'  => md5($content), // Unqiue content should guarentee unique path.
            'source'    => $sourcefield,
            'mimetype'  => 'text',
        ];
        $file = $fs->create_file_from_string($filerecord, $content);

        manager::update_object_by_hash($file->get_contenthash(), OBJECT_LOCATION_LOCAL);
        return $file;
    }

    /**
     * create_duplicated_file
     * @param string $content
     *
     * @return stored_file
     */
    protected function create_duplicated_file($content = 'test content') {
        $file = $this->create_local_file($content);
        $contenthash = $file->get_contenthash();
        $this->filesystem->copy_object_from_local_to_external_by_hash($contenthash);
        manager::update_object_by_hash($contenthash, OBJECT_LOCATION_DUPLICATED);
        return $file;
    }

    /**
     * create_remote_file
     * @param string $content
     *
     * @return stored_file
     */
    protected function create_remote_file($content = 'test content') {
        $file = $this->create_duplicated_file($content);
        $contenthash = $file->get_contenthash();
        $this->filesystem->delete_object_from_local_by_hash($contenthash);
        manager::update_object_by_hash($contenthash, OBJECT_LOCATION_EXTERNAL);
        return $file;
    }

    /**
     * create_error_file
     * @return stored_file
     */
    protected function create_error_file() {
        $file = $this->create_local_file();
        $path = $this->get_local_path_from_storedfile($file);
        unlink($path);
        manager::update_object_by_hash($file->get_contenthash(), OBJECT_LOCATION_ERROR);
        return $file;
    }

    /**
     * get_external_path_from_hash
     * @param string $contenthash
     *
     * @return mixed
     */
    protected function get_external_path_from_hash($contenthash) {
        $reflection = new \ReflectionMethod(object_file_system::class, 'get_external_path_from_hash');
        $reflection->setAccessible(true);
        return $reflection->invokeArgs($this->filesystem, [$contenthash]);
    }

    /**
     * get_external_path_from_storedfile
     * @param mixed $file
     *
     * @return mixed
     */
    protected function get_external_path_from_storedfile($file) {
        $contenthash = $file->get_contenthash();
        return $this->get_external_path_from_hash($contenthash);
    }

        /**
         * get_local_path_from_hash
         *
         * We want acces to local path for testing so we use a reflection
         * method as opposed to rewriting here.
         * @param string $contenthash
         *
         * @return mixed
         */
    protected function get_local_path_from_hash($contenthash) {
        $reflection = new \ReflectionMethod(object_file_system::class, 'get_local_path_from_hash');
        $reflection->setAccessible(true);
        return $reflection->invokeArgs($this->filesystem, [$contenthash]);
    }

    /**
     * delete_file
     * @param string $contenthash
     *
     * @return mixed
     */
    protected function delete_file($contenthash) {
        $reflection = new \ReflectionMethod(object_file_system::class, 'delete_file');
        $reflection->setAccessible(true);
        return $reflection->invokeArgs($this->filesystem, [$contenthash]);
    }

    /**
     * rename_file
     * @param mixed $currentpath
     * @param mixed $destinationpath
     *
     * @return mixed
     */
    protected function rename_file($currentpath, $destinationpath) {
        $reflection = new \ReflectionMethod(object_file_system::class, 'rename_file');
        $reflection->setAccessible(true);
        return $reflection->invokeArgs($this->filesystem, [$currentpath, $destinationpath]);
    }

    /**
     * get_local_path_from_storedfile
     * @param mixed $file
     *
     * @return mixed
     */
    protected function get_local_path_from_storedfile($file) {
        $contenthash = $file->get_contenthash();
        return $this->get_local_path_from_hash($contenthash);
    }

    /**
     * recover_file
     * @param mixed $file
     *
     * @return mixed
     */
    protected function recover_file($file) {
        $reflection = new \ReflectionMethod(object_file_system::class, 'recover_file');
        $reflection->setAccessible(true);
        return $reflection->invokeArgs($this->filesystem, [$file]);
    }

    /**
     * create_local_object
     * @param mixed $content
     *
     * @return mixed
     */
    protected function create_local_object($content = 'local object content') {
        $file = $this->create_local_file($content);
        return $this->create_object_record($file, OBJECT_LOCATION_LOCAL);
    }

    /**
     * create_duplicated_object
     * @param mixed $content
     *
     * @return mixed
     */
    protected function create_duplicated_object($content = 'duplicated object content') {
        $file = $this->create_duplicated_file($content);
        return $this->create_object_record($file, OBJECT_LOCATION_DUPLICATED);
    }

    /**
     * create_remote_object
     * @param mixed $content
     *
     * @return mixed
     */
    protected function create_remote_object($content = 'remote object content') {
        $file = $this->create_remote_file($content);
        return $this->create_object_record($file, OBJECT_LOCATION_EXTERNAL);
    }

    /**
     * create_error_object
     * @param mixed $content
     *
     * @return mixed
     */
    protected function create_error_object($content = 'error object content') {
        $file = $this->create_error_file($content);
        return $this->create_object_record($file, OBJECT_LOCATION_ERROR);
    }

    /**
     * is_locally_readable_by_hash
     * @param mixed $contenthash
     *
     * @return mixed
     */
    protected function is_locally_readable_by_hash($contenthash) {
        $localpath = $this->get_local_path_from_hash($contenthash);
        return is_readable($localpath);
    }

    /**
     * is_externally_readable_by_hash
     * @param mixed $contenthash
     *
     * @return mixed
     */
    protected function is_externally_readable_by_hash($contenthash) {
        $externalpath = $this->get_external_path_from_hash($contenthash);
        return is_readable($externalpath);
    }

    /**
     * acquire_object_lock
     * @param mixed $filehash
     * @param int $timeout
     *
     * @return mixed
     */
    protected function acquire_object_lock($filehash, $timeout = 0) {
        $reflection = new \ReflectionMethod(object_file_system::class, 'acquire_object_lock');
        $reflection->setAccessible(true);
        return $reflection->invokeArgs($this->filesystem, [$filehash, $timeout]);
    }

    /**
     * delete_draft_files
     * @param mixed $contenthash
     *
     * @return mixed
     */
    protected function delete_draft_files($contenthash) {
        global $DB;
        $DB->delete_records('files', ['contenthash' => $contenthash]);
    }

    /**
     * is_externally_readable_by_url
     * @param signed_url $signedurl
     *
     * @return mixed
     */
    protected function is_externally_readable_by_url(signed_url $signedurl) {
        try {
            $file = fopen($signedurl->url->out(false), 'r');
            if ($file === false) {
                $result = false;
            } else {
                fclose($file);
                $result = true;
            }
            return $result;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * create_object_record
     * @param stored_file $file
     * @param mixed $location
     * @return stdClass
     * @throws dml_exception
     */
    private function create_object_record(stored_file $file, $location) {
        global $DB;
        $contenthash = $file->get_contenthash();
        $objectrecord = new stdClass();
        $objectrecord->contenthash = $contenthash;
        $objectrecord->location = $location;
        $objectrecord->filesize = $file->get_filesize();
        $objectrecord->id = $DB->get_field('tool_objectfs_objects', 'id', ['contenthash' => $contenthash]);
        return $objectrecord;
    }

    /**
     * objects_contain_hash
     * @param string $contenthash
     * @return bool
     * @throws moodle_exception
     */
    protected function objects_contain_hash($contenthash) {
        $config = manager::get_objectfs_config();
        $config->filesystem = get_class($this->filesystem);
        $candidatesfinder = new candidates_finder($this->manipulator, $config);
        $candidateobjects = $candidatesfinder->get();
        foreach ($candidateobjects as $candidateobject) {
            if ($contenthash === $candidateobject->contenthash) {
                return true;
            }
        }
        return false;
    }
}
