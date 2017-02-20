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
   * Strings for component 'local_catdeleter', language 'en'.
   *
   * @package   local_catdeleter
   * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
   * @copyright Catalyst IT
   * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
   */

$string['pluginname'] = 'Object storage file System';
$string['push_objects_to_storage_task'] = 'S3 file system upload task';
$string['delete_local_objects_task'] = 'S3 file system delete local files task';
$string['pull_objects_from_storage_task'] = 'S3 file system download files task';


$string['generate_status_report_task'] = 'S3 file status report generator task';
$string['not_enabled'] = 'The S3 file system is not enabled';
$string['object_status:page'] = 'S3 file status';
$string['object_status:location'] = 'File location';
$string['object_status:files'] = 'Files';
$string['object_status:size'] = 'Total size';

$string['object_status:location:error'] = 'Missing from filedir';
$string['object_status:location:duplicated'] = 'Duplicated in S3 and filedir';
$string['object_status:location:local'] = 'Only in filedir';
$string['object_status:location:external'] = 'Only in S3';
$string['object_status:location:unknown'] = 'Unknown file location';
$string['object_status:last_run'] = 'This report was generated on {$a}';
$string['object_status:never_run'] = 'The task to generate this report has not been run.';

$string['settings:enabled'] = 'Enable S3 file system';
$string['settings:enabled_help'] = 'Enable or disable the S3 file system.';
$string['settings:awsheader'] = 'AWS S3 Settings';
$string['settings:key'] = 'Key';
$string['settings:key_help'] = 'AWS key credential.';
$string['settings:secret'] = 'Secret';
$string['settings:secret_help'] = 'AWS secret credential.';
$string['settings:bucket'] = 'Bucket';
$string['settings:bucket_help'] = 'AWS bucket to store files in.';
$string['settings:region'] = 'AWS region';
$string['settings:region_help'] = 'AWS API gateway region.';
$string['settings:filetransferheader'] = 'File Transfer Settings';
$string['settings:sizethreshold'] = 'Minimum size threshold (KB)';
$string['settings:sizethreshold_help'] = 'Minimum size threshold for transfering files to S3. If files are over this size they will be transfered to S3.';
$string['settings:minimumage'] = 'Minimum age';
$string['settings:minimumage_help'] = 'Minimum age that a file must exist on the local file system before it will be considered for transfer.';
$string['settings:deletelocal'] = 'Delete local files';
$string['settings:deletelocal_help'] = 'Delete local files once they are in S3 after the consistency delay.';
$string['settings:consistencydelay'] = 'Consistency delay';
$string['settings:consistencydelay_help'] = 'How long a file must existed after being transfered to S3 before they are a candidate for deletion locally.';
$string['settings:maxtaskruntime'] = 'Maximum task runtime';
$string['settings:maxtaskruntime_help'] = 'Maximum runtime for all S3 related tasks; pushing to S3, pulling from S3 and cleaning files that are in S3.';
$string['settings:prefersss'] = 'Prefer S3 files';
$string['settings:prefersss_help'] = 'If a file is stored both locally and in S3, read from S3. This is setting is mainly for testing purposes and introduces overhead to check the location.';
$string['settings:loggingheader'] = 'Logging Settings';
$string['settings:logging'] = 'Enable logging';
$string['settings:logging_help'] = 'Log file access to the php log.';
$string['settings:connectionsuccess'] = 'Could establish connection to the AWS S3 bucket.';
$string['settings:connectionfailure'] = 'Could not establish connection to the AWS S3 bucket.';
$string['settings:writefailure'] = 'Could not write object to the S3 bucket. ';
$string['settings:readfailure'] = 'Could not read object from the S3 bucket. ';
$string['settings:deletesuccess'] = 'Could delete object from the S3 bucket - It is not recommended for the AWS user to have delete permissions. ';
$string['settings:permissioncheckpassed'] = 'Permissions check passed.';
$string['settings:handlernotset'] = '$CFG->filesystem_handler_class is not set, the file system will not be able to read from S3. Background tasks will still function.';