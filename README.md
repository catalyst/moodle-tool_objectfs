<a href="https://travis-ci.org/catalyst/moodle-tool_sssfs">
<img src="https://travis-ci.org/catalyst/moodle-tool_sssfs.svg?branch=master">
</a>

# moodle-tool_sssfs

An AWS S3 file system for Moodle. This plugin implements the file system as well as the background tasks which pushes files to and from S3.

## Requirements
- Moodle version 31 with this patch applied from this tracker: https://tracker.moodle.org/browse/MDL-46375

## Installation
1. Clone this repository into admin/tool/sssfs
2. Install the plugin throught the moodle GUI.
3. Place the following line inside your moodle config.php:
<pre>
$CFG->filesystem_handler_class = '\tool_sssfs\sss_file_system';
</pre>

## Configuration
Go to Site Administration -> Plugins -> Admin tools -> S3 File System. Descriptions for the various settings are as follows:

- **Key**: AWS credential key
- **Secret**: AWS credential secret
- **Bucket**: S3 bucket name to store files in
- **AWS region**: AWS API endpoint region to use.
- **Minimum size threshold (KB)**: Minimum size threshold for transferring files to S3. If files are over this size they will be transfered to S3.
- **Minimum age**: Minimum age that a file must exist on the local file system before it will be considered for transfer.
- **Maximum task runtime**: Maximum runtime for all S3 related tasks; pushing to S3, pulling from S3 and cleaning files that are in S3 from the local file system.
- **Delete local files**: Delete local files once they are in S3 after the consistency delay.
- **Consistency delay**: How long a file must existed after being transfered to S3 before they are a candidate for deletion locally.
- **Enable logging**: Log file access to the php log.


TODO: MD5 check on uploading and deleting.
TODO: S3 backups - can it be configured to be immutable write.
TODO: implement logging.
TODO: implement pulling task.


