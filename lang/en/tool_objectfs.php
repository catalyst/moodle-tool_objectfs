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
$string['pluginsettings'] = 'Plugin Settings';
$string['privacy:metadata'] = 'The tool objectfs plugin does not store any personal data.';
$string['push_objects_to_storage_task'] = 'Object file system upload task';
$string['delete_orphaned_object_metadata_task'] = 'Object file system delete orphaned metadata task';
$string['delete_local_objects_task'] = 'Object file system delete local objects task';
$string['delete_local_empty_directories_task'] = 'Object file system delete local empty directories task';
$string['pull_objects_from_storage_task'] = 'Object file system download objects task';
$string['recover_error_objects_task'] = 'Object error recovery task';
$string['check_objects_location_task'] = 'Object file system check objects location task';
$string['orphan_objects_task'] = 'Object file system orphan objects task';

$string['generate_status_report_task'] = 'Object status report generator task';
$string['not_enabled'] = 'The object file system background tasks are not enabled. No objects will move location until you do.';
$string['client_not_available'] = 'The configured remote client is not available. Please ensure it is installed correctly.';

$string['object_status:page'] = 'Object status';
$string['object_status:location'] = 'Object location';
$string['object_status:locationhistory'] = 'Object location history';
$string['object_status:log_size'] = 'Log size';
$string['object_status:mime_type'] = 'Mime type';
$string['object_status:count'] = 'Objects';
$string['object_status:size'] = 'Total size';
$string['object_status:runningsize'] = 'Running total';

$string['page:missingfiles'] = 'Missing from filedir and external storage files';

$string['filename:missingfiles'] = 'missingfiles';
$string['fixturefilemissing'] = 'The fixture file is missing';

$string['object_status:location:error'] = 'Missing from filedir and external storage (<a href="/admin/tool/objectfs/missing_files.php">view files</a>)';
$string['object_status:location:duplicated'] = 'Duplicated in filedir and external storage';
$string['object_status:location:local'] = 'Marked as only in filedir';
$string['object_status:location:orphaned'] = 'Marked as orphaned (not in the {files} table)';
$string['object_status:location:external'] = 'Only in external storage';
$string['object_status:location:unknown'] = 'Unknown object location';
$string['object_status:location:total'] = 'Total';
$string['object_status:location:localcount'] = 'Local (count)';
$string['object_status:location:localsize'] = 'Local (size)';
$string['object_status:location:duplicatedcount'] = 'Duplicated (count)';
$string['object_status:location:duplicatedsize'] = 'Duplicated (size)';
$string['object_status:location:orphanedcount'] = 'Orphaned (count)';
$string['object_status:location:orphanedsize'] = 'Orphaned (size)';
$string['object_status:location:orphanedsizeunknown'] = 'Unknown';
$string['object_status:location:externalcount'] = 'External (count)';
$string['object_status:location:externalsize'] = 'External (size)';
$string['object_status:location:missingcount'] = 'Error (count)';
$string['object_status:location:missingsize'] = 'Error (size)';
$string['object_status:location:totalcount'] = 'Total (count)';
$string['object_status:location:totalsize'] = 'Total (size)';
$string['object_status:location:filedircount'] = 'Filedir (count)';
$string['object_status:location:filedirsize'] = 'Filedir (size)';
$string['object_status:location:deltacount'] = 'Delta (count)';
$string['object_status:location:deltasize'] = 'Delta (size)';

$string['object_status:filedir'] = 'Filedir';
$string['object_status:delta:a'] = 'Delta (filedir - objectfs)';
$string['object_status:delta:b'] = 'Delta (objectfs - filedir)';
$string['object_status:filedir:count'] = 'File counting';
$string['object_status:filedir:update'] = 'Update stats';


$string['object_status:last_run'] = 'This report was generated on {$a}';
$string['object_status:never_run'] = 'The task to generate this report has not been run.';

$string['rangerequestfailed'] = '<strong>URL</strong>: {$a->url}<br><strong>HTTP code</strong>: {$a->httpcode}<br><strong>Details</strong>: {$a->details}';
$string['settings'] = 'Settings';
$string['settings:enabletasks'] = 'Enable background transfer tasks';
$string['settings:enabletasks_help'] = 'Enable or disable the object file system tasks which move files between the filedir and external object storage.';
$string['settings:enablelogging'] = 'Enable real time logging';
$string['settings:enablelogging_help'] = 'Enable or disable file system logging. Will output diagnostic information to the php error log. ';
$string['settings:useproxy'] = 'Use proxy';
$string['settings:useproxy_help'] = 'Objectfs can use configured proxy to reach external storage.';

$string['settings:generalheader'] = 'General Settings';

$string['settings:clientnotavailable'] = 'Client for current file system is not available. Please install the required dependencies if this is the desired object storage client.';

$string['settings:clientselection:header'] = 'Storage File System Selection';
$string['settings:clientselection:title'] = 'Storage File System';
$string['settings:clientselection:title_help'] = 'The storage file system. This is also the active file system for the background tasks.';
$string['settings:clientselection:mismatchfilesystem'] = 'This setting should match $CFG->alternative_file_system_class';
$string['settings:clientselection:filesystemnotdefined'] = '$CFG->alternative_file_system_class should be set in your Moodle config.php';
$string['settings:clientselection:fsapinotbackported'] = 'File system API (MDL-46375) is not backported. Follow up <a href="https://github.com/catalyst/moodle-tool_objectfs#backporting">Backporting</a> README section.';

$string['settings:aws:header'] = 'Amazon S3 Settings';
$string['settings:aws:key'] = 'Key';
$string['settings:aws:key_help'] = 'Amazon S3 key credential.';
$string['settings:aws:secret'] = 'Secret';
$string['settings:aws:secret_help'] = 'Amazon S3 secret credential.';
$string['settings:aws:bucket'] = 'Bucket';
$string['settings:aws:bucket_help'] = 'Amazon S3 bucket to store files in.';
$string['settings:aws:region'] = 'region';
$string['settings:aws:region_help'] = 'Amazon S3 API gateway region.';
$string['settings:aws:base_url'] = 'Base URL';
$string['settings:aws:base_url_help'] = 'Alternate url for cnames or s3 compatible endpoints. Leave blank for normal S3 use.';
$string['settings:aws:upgradeneeded'] = 'Please upgrade \'local_aws\' plugin to the latest supported version.';
$string['settings:aws:installneeded'] = 'Please install \'local_aws\' plugin.';
$string['settings:aws:usesdkcreds'] = 'Use the default credential provider chain to find AWS credentials';
$string['settings:aws:sdkcredsok'] = 'AWS credentials found. This setting can be safely enabled.';
$string['settings:aws:sdkcredserror'] = 'Couldn\'t find AWS credentials. It\'s unsafe to enable this setting. Follow up <a href="https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/guide_credentials.html">AWS documentation</a>.';
$string['settings:aws:key_prefix'] = 'Prefix to use in bucket';
$string['settings:aws:key_prefix_help'] = 'Prefix to use inside Amazon S3 bucket. Must end with trailing slash when set. Leave blank to use root of bucket.';

$string['settings:do:header'] = 'DigitalOcean Spaces Settings';
$string['settings:do:key'] = 'Key';
$string['settings:do:key_help'] = 'DO Spaces key credential.';
$string['settings:do:secret'] = 'Secret';
$string['settings:do:secret_help'] = 'DO Spaces secret credential.';
$string['settings:do:space'] = 'Space';
$string['settings:do:space_help'] = 'DO Space to store files in.';
$string['settings:do:region'] = 'Region';
$string['settings:do:region_help'] = 'DO Spaces API gateway region.';

$string['settings:azure:header'] = 'Azure Blob Storage Settings';
$string['settings:azure:accountname'] = 'Account name';
$string['settings:azure:accountname_help'] = 'The name of the storage account.';
$string['settings:azure:container'] = 'Container name';
$string['settings:azure:container_help'] = 'The name of the container that will store the blobs.';
$string['settings:azure:sastoken'] = 'Shared Access Signature';
$string['settings:azure:sastoken_help'] = 'This Shared Access Signature should have the following two capabilites only. Read, write.';

$string['settings:openstack:header'] = 'Openstack Swift Object Storage Settings';
$string['settings:openstack:username'] = 'User name';
$string['settings:openstack:username_help'] = 'The username of the storage account.';
$string['settings:openstack:password'] = 'Account password';
$string['settings:openstack:password_help'] = 'The password of the storage account user.';
$string['settings:openstack:authurl'] = 'Authentication API URL';
$string['settings:openstack:authurl_help'] = 'The URL to the Authentication API URL';
$string['settings:openstack:region'] = 'Openstack Region';
$string['settings:openstack:region_help'] = 'The Openstack availability region';
$string['settings:openstack:tenantname'] = 'Tenant name';
$string['settings:openstack:tenantname_help'] = 'The Openstack Tenant Name';
$string['settings:openstack:projectid'] = 'Project ID';
$string['settings:openstack:projectid_help'] = 'The Openstack Project ID';
$string['settings:openstack:container'] = 'Container name';
$string['settings:openstack:container_help'] = 'The name of the container that will store the objects.';

$string['settings:filetransferheader'] = 'File Transfer Settings';
$string['settings:sizethreshold'] = 'Minimum size threshold (bytes)';
$string['settings:sizethreshold_help'] = 'Minimum size threshold for transfering objects to external object storage. If objects are over this size they will be transfered.';
$string['settings:batchsize'] = 'Number files in one batch';
$string['settings:batchsize_help'] = 'Number of files to be transferred in one cron run';
$string['settings:maxorphanedage'] = 'Max orphaned object age';
$string['settings:maxorphanedage_help'] = 'If set to zero, this will not delete old orphaned metadata for objects. Otherwise, it will remove these records as they are no longer relevant. An orphaned object is one where the metadata exists on the {tool_objectfs_objects} table but referenced file no longer exists.';
$string['settings:minimumage'] = 'Minimum age';
$string['settings:minimumage_help'] = 'Minimum age that a object must exist on the local filedir before it will be considered for transfer.';
$string['settings:deleteexternal'] = 'Delete external objects';
$string['settings:deleteexternal_help'] = 'Delete external objects when the file is deleted in Moodle. This is not recommended if you intend to share one object store between multiple environments, however this is a requirement for GDPR compliance.
<br/>Delete external file on orphan clean-up - This will delete the external file when the delete orphaned metadata task runs.
<br/>Delete completely - will tell the external storage to delete the file immediately - (use with caution! - if the same file is being uploaded while being deleted, issues could occur.)';
$string['settings:deletelocal'] = 'Delete local objects';
$string['settings:deletelocal_help'] = 'Delete local objects once they are in external object storage after the consistency delay.';
$string['settings:consistencydelay'] = 'Consistency delay';
$string['settings:consistencydelay_help'] = 'How long an object must have existed after being transfered to external object storage before they are a candidate for deletion locally.';
$string['settings:maxtaskruntime'] = 'Maximum transfer task runtime';
$string['settings:maxtaskruntime_help'] = 'Background tasks handle the transfer of objects to and from external object storage. This setting controlls the maximum runtime for all object transfer related tasks to process 1000 files.';
$string['settings:preferexternal'] = 'Prefer external objects';
$string['settings:preferexternal_help'] = 'If a file is stored both locally and in external object storage, read from external\. This is setting is mainly for testing purposes and introduces overhead to check the location.';

$string['settings:presignedurl:header'] = 'Pre-Signed URLs Settings';
$string['settings:presignedurl:coresupport'] = 'Feature is not supported by core, you need to cherry pick: <a href="https://github.com/catalyst/moodle-tool_objectfs#allow-support-for-xsendfile-in-alternative-file-system">xsendfile support</a>';
$string['settings:presignedurl:filetypesclass'] = 'Pre-Signed URLs can\'t be configured, you need to backport MDL-53240';
$string['settings:presignedurl:enablepresignedurlschoice'] = 'Signing method';
$string['settings:presignedurl:warning'] = 'Before enabling Pre-Signed URL, please, make sure that all tests are passed successfully: ';
$string['settings:presignedurl:enablepresignedurls'] = 'Enable Pre-Signed URLs';
$string['settings:presignedurl:enablepresignedurls_help'] = 'Enable Pre-Signed URLs to request content directly from external storage.';
$string['settings:presignedurl:expirationtime'] = 'Pre-Signed URL expiration time';
$string['settings:presignedurl:expirationtime_help'] = 'All expirations are inherited from the Expires header sent by Moodle. If no headers are sent the Expiration defaults to this setting.';
$string['settings:presignedurl:presignedminfilesize'] = 'Minimum size for Pre-Signed URL (bytes)';
$string['settings:presignedurl:presignedminfilesize_help'] = 'Minimum file size to be redirected to Pre-Signed URL.';
$string['settings:presignedurl:proxyrangerequests'] = 'Proxy range requests';
$string['settings:presignedurl:proxyrangerequests_help'] = 'Pre-Signed URLs do not need to be enabled. S3 signing method will be used for this feature.';
$string['settings:presignedurl:xsendfilefile'] = 'Backport MDL-68342 to get benefits of this setting.';
$string['settings:presignedurl:testrangeok'] = 'Successfully tested range request.';
$string['settings:presignedurl:testrangeerror'] = 'Test range request failed';

$string['settings:presignedurl:enablepresigneds3urls'] = 'S3 Pre-Signed URLs';
$string['settings:presignedurl:enablepresigneds3urls_help'] = 'Enable Pre-Signed S3 URLs to request content directly from external storage.';

$string['settings:presignedurl:whitelist'] = 'Pre-Signed URL whitelist.';
$string['settings:presignedurl:whitelist_help'] = 'Only whitelisted file extensions will be redirected to Pre-Signed URL.';
$string['settings:presignedurl:deletedsuccess'] = 'Files deleted successfully.';
$string['settings:presignedurl:deletefiles'] = 'Delete test files.';

$string['settings:presignedcloudfronturl:header'] = 'Cloudfront Settings (Experimental)';
$string['settings:presignedcloudfronturl:warning'] = 'Before enabling Cloudfront Pre-Signed URL, please, make sure that all tests are passed successfully: ';
$string['settings:presignedcloudfronturl:enablepresignedcloudfronturls'] = 'Cloudfront Pre-Signed URLs';
$string['settings:presignedcloudfronturl:enablepresignedcloudfronturls_help'] = 'Enable Cloudfront Pre-Signed URLs by setting up a Cloudfront Distribution profile at AWS.';
$string['settings:presignedcloudfronturl:cloudfront_resource_domain'] = 'DOMAIN (inc. https://)';
$string['settings:presignedcloudfronturl:cloudfront_resource_domain_help'] = 'Enter the domain name from which resources are requested at Cloudfront (refer to AWS Cloudfront Distribution)';
$string['settings:presignedcloudfronturl:cloudfront_key_pair_id'] = 'Key_Pair ID from AWS';
$string['settings:presignedcloudfronturl:cloudfront_key_pair_id_help'] = 'This is generated using AWS account \'root\' user (along with the private key .pem file).';
$string['settings:presignedcloudfronturl:cloudfront_private_key_pem'] = 'PRIVATE Key .pem';
$string['settings:presignedcloudfronturl:cloudfront_private_key_pem_help'] = '
Private key in .pem format either inline or the filename including the pem extension e.g. <code>cloudfront.pem</code> which should be located under <code>dataroot/objectfs/</code>
<pre>
-----BEGIN RSA PRIVATE KEY-----
S3O3BrpoUCwYTF5Vn9EQhkjsu8s...
-----END RSA PRIVATE KEY-----
</pre>';
$string['settings:presignedcloudfronturl:cloudfront_custom_policy_json'] = '\'custom policy\' JSON (optional)';
$string['settings:presignedcloudfronturl:cloudfront_custom_policy_json_help'] = 'AWS Distribution "custom policy" JSON (advanced!)';
$string['settings:presignedcloudfronturl:cloudfront_pem_found'] = 'Cloudfront private key content (.pem) is valid. OK';
$string['settings:presignedcloudfronturl:cloudfront_pem_not_found'] = 'Cloudfront private key (.pem) is invalid.';
$string['settings:relyonorphancleanup'] = 'Delete external file on orphan clean-up';
$string['settings:fulldelete'] = 'Delete completely';

$string['pleaseselect'] = 'Please, select';
$string['presignedurl_testing:page'] = 'Pre-Signed URL Testing';
$string['presignedurl_testing:presignedurlsnotsupported'] = 'Pre-Signed URLs are not supported by chosen storage file system.';
$string['presignedurl_testing:test1'] = '1) Test links below to download file with contenthash as its name:';
$string['presignedurl_testing:test2'] = '2) Test links below to download file with original file name:';
$string['presignedurl_testing:test3'] = '3) Test links below to open content inline:';
$string['presignedurl_testing:test4'] = '4) In this block IFrames should be visible and workable:';
$string['presignedurl_testing:test5'] = '5) Test Expires header using IFrames:';
$string['presignedurl_testing:downloadfile'] = 'Download file';
$string['presignedurl_testing:openinbrowser'] = 'Open file in browser';
$string['presignedurl_testing:fileiniframe'] = 'file in Iframe';
$string['presignedurl_testing:iframesnotsupported'] = 'Your browser does not support IFrames';
$string['presignedurl_testing:objectfssettings'] = 'Objectfs settings';
$string['presignedurl_testing:checkconnectionsettings'] = 'Check connection settings at ';
$string['presignedurl_testing:checkclientsettings'] = 'Check client settings at ';
$string['presignedurl_testing:checkfssettings'] = 'Check filesystem settings at ';

$string['settings:connectionsuccess'] = 'Could establish connection to object storage. ';
$string['settings:connectionfailure'] = 'Could not establish connection to object storage. {$a}';
$string['settings:writefailure'] = 'Could not write permissions check file from object storage. ';
$string['settings:connectionreadfailure'] = 'Could not read connection check file from object storage. ';
$string['settings:permissionreadfailure'] = 'Could not read permissions check file from object storage. ';
$string['settings:deletesuccess'] = 'Could delete file from object storage - It is not recommended for the user to have delete permissions. ';
$string['settings:deleteerror'] = 'Could not delete permissions check file from object storage. ';
$string['settings:permissioncheckpassed'] = 'Permissions check passed.';
$string['settings:handlernotset'] = '$CFG->alternative_file_system_class is not set, the file system will not be able to read from object storage. Background tasks can still function.';

$string['settings:testingheader'] = 'Test Settings';
$string['settings:testingdescr'] = 'This setting is mainly for testing purposes and introduces overhead to check the location.';

$string['settings:error:numeric'] = 'Please enter a number which is greater than or equal 0.';
$string['settings:notconfigured'] = 'Missing configuration.';
$string['total_deleted_dirs'] = 'Total number of deleted directories: ';
$string['backportfiletypesclass'] = 'Backport MDL-53240 is missing. Follow up https://github.com/catalyst/moodle-tool_objectfs#applying-core-patches';

$string['check:proxyrangerequestsdisabled'] = 'The proxy range request setting is disabled.';
$string['checkproxy_range_request'] = 'Pre-signed URL range request proxy';

$string['checktoken_expiry'] = 'Token expiry';
$string['check:tokenexpiry:expiresin'] = 'Token expires in {$a->dayssince} days on {$a->time}';
$string['check:tokenexpiry:expired'] = 'Token expired for {$a->dayssince} days. Expired on {$a->time}';
$string['check:tokenexpiry:na'] = 'Token expired not implemented for filesystem, or no token is set';
$string['settings:tokenexpirywarnperiod'] = 'Token expiry warn period';

$string['settings:taggingheader'] = 'Tagging settings';
$string['settings:taggingenabled'] = 'Tagging enabled';
$string['checktagging_status'] = 'Object tagging';
$string['check:tagging:ok'] = 'Object tagging ok';
$string['check:tagging:na'] = 'Tagging not enabled or is not supported by file system';
$string['check:tagging:error'] = 'Error trying to tag object';
$string['settings:maxtaggingperrun'] = 'Object tagging adhoc sync maximum objects per run';
$string['settings:maxtaggingperrun:desc'] = 'The maximum number of objects to sync tags for per tagging sync adhoc task iteration.';
$string['settings:maxtaggingiterations'] = 'Object tagging adhoc sync maximum number of iterations ';
$string['settings:maxtaggingiterations:desc'] = 'The maximum number of times the tagging sync adhoc task will requeue itself. To avoid accidental infinite runaway.';
$string['settings:overrideobjecttags'] = 'Allow object tag override';
$string['settings:overrideobjecttags:desc'] = 'Allows ObjectFS to overwrite tags on objects that already exist in the external store.';
$string['tagsource:environment'] = 'Environment defined by $CFG->objectfs_environment_name, currently: "{$a}".';
$string['tagsource:mimetype'] = 'File mimetype as stored in {files} table';
$string['settings:tagsources'] = 'Tag sources';
$string['settings:taggingstatus'] = 'Tagging status';
$string['task:triggerupdateobjecttags'] = 'Queue adhoc task to update object tags';
$string['settings:tagging:help'] = 'Object tagging allows extra metadata to be attached to objects in the external store. Please read TAGGING.md in the plugin Github repository for detailed setup and considerations. This is currently only supported by the S3 external client.';
$string['object_status:tag_count'] = 'Object tags';
$string['tagsyncstatus:error'] = 'Errored';
$string['tagsyncstatus:notrequired'] = 'Not required / synced';
$string['tagsyncstatus:needssync'] = 'Waiting for sync';
$string['settings:taggingstatuscounts'] = 'Tag sync status overview';
$string['tagging:migration:notsupported'] = 'Tagging not enabled or supported by filesystem. Cannot execute tag migration task';
$string['tagging:migration:invaliditerations'] = 'Invalid iteration number or iteration count';
$string['tagging:migration:limitreached'] = 'Current iteration {$a} is >= the maximum number of iterations. Please investigate if this is expected and you need to increase the limit, or if there is a problem syncing the tags causing an infinite loop';
$string['settings:taggingmigrationstatus'] = 'Tagging adhoc migration progress';
$string['tagging:migration:nothingrunning'] = 'No tagging migration adhoc tasks are currently running';
$string['tagging:migration:help'] = 'Run the <code>trigger_update_object_tags</code> scheduled task from the frontend or CLI to start a migration task.';
$string['table:taskid'] = 'Task ID';
$string['table:iteration'] = 'Iteration number';
$string['table:status'] = 'Status';
$string['table:objectcount'] = 'Object count';
$string['table:tagsource'] = 'Tag source';
$string['table:tagsourcemeaning'] = 'Description';
$string['status:waiting'] = 'Waiting';
$string['status:running'] = 'Running';
$string['status:failing'] = 'Faildelay {$a}';
