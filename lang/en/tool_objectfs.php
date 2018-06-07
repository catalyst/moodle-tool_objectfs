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
//

  /**
   * Strings for component 'tool_objectfs', language 'en'.
   *
   * @package   tool_objectfs
   * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
   * @copyright Catalyst IT
   * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
   */

$string['pluginname'] = 'Object storage file system';
$string['push_objects_to_storage_task'] = 'Object file system upload task';
$string['delete_local_objects_task'] = 'Object file system delete local objects task';
$string['pull_objects_from_storage_task'] = 'Object file system download objects task';
$string['recover_error_objects_task'] = 'Object error recovery task';

$string['generate_status_report_task'] = 'Object status report generator task';
$string['not_enabled'] = 'The object file system background tasks are not enabled. No objects will move location until you do.';

$string['object_status:page'] = 'Object status';
$string['object_status:location'] = 'Object location';
$string['object_status:files'] = 'Objects';
$string['object_status:size'] = 'Total size';

$string['page:missingfiles'] = 'Missing from filedir and external storage files';

$string['filename:missingfiles'] = 'missingfiles';

$string['object_status:location:error'] = 'Missing from filedir and external storage (<a href="/admin/tool/objectfs/missing_files.php">view files</a>)';
$string['object_status:location:duplicated'] = 'Duplicated in filedir and external storage';
$string['object_status:location:local'] = 'Only in filedir';
$string['object_status:location:external'] = 'Only in external storage';
$string['object_status:location:unknown'] = 'Unknown object location';
$string['object_status:location:total'] = 'Total';


$string['object_status:last_run'] = 'This report was generated on {$a}';
$string['object_status:never_run'] = 'The task to generate this report has not been run.';

$string['settings'] = 'Settings';
$string['settings:enabletasks'] = 'Enable transfer tasks';
$string['settings:enabletasks_help'] = 'Enable or disable the object file system tasks which move files between the filedir and external object storage.';
$string['settings:enablelogging'] = 'Enable real time logging';
$string['settings:enablelogging_help'] = 'Enable or disable file system logging. Will output diagnostic information to the php error log. ';

$string['settings:generalheader'] = 'General Settings';

$string['settings:clientnotavailable'] = 'The configured client \'{$a}\' is not available. Please install the required dependencies.';

$string['settings:clientselection:header'] = 'Storage File System Selection';
$string['settings:clientselection:title'] = 'Storage File System';
$string['settings:clientselection:title_help'] = 'The storage file system. This is also the active file system for the background tasks.';
$string['settings:clientselection:matchfilesystem'] = 'This setting matches $CFG->alternative_file_system_class';
$string['settings:clientselection:mismatchfilesystem'] = 'This setting does not match $CFG->alternative_file_system_class';

$string['settings:aws:header'] = 'Amazon S3 Settings';
$string['settings:aws:key'] = 'Key';
$string['settings:aws:key_help'] = 'Amazon S3 key credential.';
$string['settings:aws:secret'] = 'Secret';
$string['settings:aws:secret_help'] = 'Amazon S3 secret credential.';
$string['settings:aws:bucket'] = 'Bucket';
$string['settings:aws:bucket_help'] = 'Amazon S3 bucket to store files in.';
$string['settings:aws:region'] = 'region';
$string['settings:aws:region_help'] = 'Amazon S3 API gateway region.';

$string['settings:azure:header'] = 'Azure Blob Storage Settings';
$string['settings:azure:accountname'] = 'Account name';
$string['settings:azure:accountname_help'] = 'The name of the storage account.';
$string['settings:azure:container'] = 'Container name';
$string['settings:azure:container_help'] = 'The name of the container that will store the blobs.';
$string['settings:azure:sastoken'] = 'Shared Access Signature';
$string['settings:azure:sastoken_help'] = 'This Shared Access Signature should have the following two capabilites only. Read, write.';

$string['settings:filetransferheader'] = 'File Transfer Settings';
$string['settings:sizethreshold'] = 'Minimum size threshold (KB)';
$string['settings:sizethreshold_help'] = 'Minimum size threshold for transfering objects to external object storage. If objects are over this size they will be transfered.';
$string['settings:minimumage'] = 'Minimum age';
$string['settings:minimumage_help'] = 'Minimum age that a object must exist on the local filedir before it will be considered for transfer.';
$string['settings:deletelocal'] = 'Delete local objects';
$string['settings:deletelocal_help'] = 'Delete local objects once they are in external object storage after the consistency delay.';
$string['settings:consistencydelay'] = 'Consistency delay';
$string['settings:consistencydelay_help'] = 'How long an object must have existed after being transfered to external object storage before they are a candidate for deletion locally.';
$string['settings:maxtaskruntime'] = 'Maximum transfer task runtime';
$string['settings:maxtaskruntime_help'] = 'Background tasks handle the transfer of objects to and from external object storage. This setting controlls the maximum runtime for all object transfer related tasks.';
$string['settings:preferexternal'] = 'Prefer external objects';
$string['settings:preferexternal_help'] = 'If a file is stored both locally and in external object storage, read from external\. This is setting is mainly for testing purposes and introduces overhead to check the location.';

$string['settings:connectionsuccess'] = 'Could establish connection to the external object storage.';
$string['settings:connectionfailure'] = 'Could not establish connection to the external object storage.';
$string['settings:writefailure'] = 'Could not write object to the external object storage. ';
$string['settings:readfailure'] = 'Could not read object from the external object storage. ';
$string['settings:deletesuccess'] = 'Could delete object from the external object storage - It is not recommended for the user to have delete permissions. ';
$string['settings:deleteerror'] = 'Could not delete object from the external object storage. ';
$string['settings:permissioncheckpassed'] = 'Permissions check passed.';
$string['settings:handlernotset'] = '$CFG->alternative_file_system_class is not set, the file system will not be able to read from the external object storage. Background tasks can still function.';
