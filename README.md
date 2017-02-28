<a href="https://travis-ci.org/catalyst/moodle-tool_objectfs">
<img src="https://travis-ci.org/catalyst/moodle-tool_objectfs.svg?branch=master">
</a>

# moodle-tool_objectfs

A remote object storage file system for Moodle. Intended to provide a plug-in that can be installed and configured to work with any supported remote object storage solution.

* [Use cases](#use-cases)
  * [Offloading large and old files to save money](#offloading-large-and-old-files-to-save-money)
  * [Sharing files across moodles to save disk](#sharing-files-across-moodles-to-save-disk)
  * [Sharing files across environments to save time](#sharing-files-across-environments-to-save-time)
  * [Sharing files with data washed environments](#sharing-files-with-data-washed-environments)
* [Installation](#installation)
* [Currently supported object stores](#currently-supported-object-stores)
  * [Roadmap](#roadmap)
  * [Amazon S3](#amazon-s3)
* [Moodle configuration](#moodle-configuration)
  * [General Settings](#general-settings)
  * [File Transfer settings](#file-transfer-settings)
  * [Amazon S3 settings](#amazon-s3-settings)
* [Backporting](#backporting)
* [Crafted by Catalyst IT](#crafted-by-catalyst-it)

## Use cases
There are a number of different ways you can use this plug in. See [Recommended use case settings](#recommended-use-case-settings) for recommended settings for each one.

### Offloading large and old files to save money

Disk can be expensive, so a simple use case is we simply want to move some of the largest and oldest files off local disk to somewhere cheaper. But we still want the convenience and performance of having the majority of files local, especially if you are hosting on-prem where the latency or bandwidth to the remote filesystem may not be great.

### Sharing files across moodles to save disk

Many of our clients have multiple moodle instances, and there is much duplicated content across instances. By pointing multiple moodles at the same remote filesystem, and not allowing deletes, then large amounts of content can be de-duplicated.

### Sharing files across environments to save time

Some of our clients moodles are truly massive. We also have multiple environments for various types of testing, and often have ad hoc environments created on demand. Not only do we not want to have to store duplicated files, but we also want refreshing data to new environments to be as fast as possible.

Using this plugin we can configure production to have full read write to the remote filesystem and store the vast bulk of content remotely. In this setup the latency and bandwidth isn't an issue as they are colocated. The local filedir on disk would only consist of small or fast churning files such as course backups. A refresh of the production data back to a staging environment can be much quicker now as we skip the sitedir clone completely and stage is simple configured with readonly access to the production filesystem. Any files it creates would only be writen to it's local filesystem which can then be discarded when next refreshed.

### Sharing files with data washed environments

Often you want a sanitised version of the data for giving to developers or other 3rd parties to remove or obfuscate sensitive content. This plugin is designed to work in this scenario too where the 3rd party gets a 'cleaned' DB, and can still point to the production remote filesystem with readonly credentials. As they cannot query the filesystem directly and must know the content hash of any content in order to access a file, there is very low risk of them accessing sensitive content.

https://github.com/catalyst/moodle-local_datacleaner

## Installation
1. If not on Moodle 3.3, backport the file system API. See [Backporting](#backporting)
1. Setup your remote object storage. See [Remote object storage setup](#remote-object-storage-setup)
1. Clone this repository into admin/tool/objectfs
2. Install the plugin through the moodle GUI.
3. Configure the plugin. See [Moodle configuration](#moodle-configuration)
4. Place the following line inside your Moodle config.php:

```php
$CFG->alternative_file_system_class = '\tool_objectfs\s3_file_system';
```
## Currently supported object stores

### Roadmap

There is support for more object stores planed, in particular enabling Openstack deployments.

### Amazon S3

*Amazon S3 bucket setup*

- Create an Amazon S3 bucket.
- The AWS Users access policy should mirror the policy listed below.
- Replace 'bucketname' with the name of your S3 bucket.

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": ["s3:ListBucket"],
      "Resource": ["arn:aws:s3:::bucketname"]
    },
    {
      "Effect": "Allow",
      "Action": [
        "s3:PutObject",
        "s3:GetObject",
        "s3:DeleteObject"
      ],
      "Resource": ["arn:aws:s3:::bucketname/*"]
    }
  ]
}
```

## Moodle configuration
Go to Site Administration -> Plugins -> Admin tools -> Object storage file system. Descriptions for the various settings are as follows:

### General Settings
- **Enable file transfer tasks**: Enable or disable the object file system tasks which move files between the filedir and remote object storage.
- **Maximum task runtime**: Background tasks handle the transfer of objects to and from remote object storage. This setting controls the maximum runtime for all object transfer related tasks.
- **Prefer remote objects**: If a file is stored both locally and in remote object storage, read from remote. This is setting is mainly for testing purposes and introduces overhead to check the location.

### File Transfer settings
These settings control the movement of files to and from object storage.

- **Minimum size threshold (KB)**: Minimum size threshold for transferring objects to remote object storage. If objects are over this size they will be transfered.
- **Minimum age**: Minimum age that a object must exist on the local filedir before it will be considered for transfer.
- **Delete local objects**: Delete local objects once they are in remote object storage after the consistency delay.
- **Consistency delay**: How long an object must have existed after being transfered to remote object storage before they are a candidate for deletion locally.

### Amazon S3 settings
S3 specific settings
- **Key**: AWS credential key
- **Secret**: AWS credential secret
- **Bucket**: S3 bucket name to store files in
- **AWS region**: AWS API endpoint region to use.


## Backporting

If you are on an older moodle then you can backport the nessesary API's in order to support this plugin. Use with caution!

#### Moodle 2.7 only
1. Cherry pick [MDL-49627](https://tracker.moodle.org/browse/MDL-49627):
[MDL-49627 - part 1](https://github.com/moodle/moodle/commit/b7067f065e6ce8d7587039094259ace3e0804663),
[MDL-49627 - part 2](https://github.com/moodle/moodle/commit/2b53b13ff7b7cb98f81d5ef98214a91dedc124af)

2. Follow steps in section below.


#### Moodle 2.7 - 3.3
1. Cherry pick the file system API patch: [MDL-46375](https://tracker.moodle.org/browse/MDL-46375):
[MDL-46375 - part 1](https://github.com/moodle/moodle/commit/16a34ae1892014a6ca3055a95ac7310442529a6c),
[MDL-46375 - part 2](https://github.com/moodle/moodle/commit/0c03db6a32fb217756e091b691f1e885b608781b)
2. If you need tests to pass see [Test compatibility](test-compatibility)


#### PHPUnit test compatibility
The file system API patch introduces tests that use:
- setExpectedExceptionRegExp() which needs phpunit 4.3
- setExpectedException() which needs phpunit 5.2 which needs needs php 5.6 (Ubuntu 14.04 runs 5.5.9)

But different core versions of Moodle may require lower versions or you may be on 14.04.

By applying a combination of patches to the new file system API tests and tweaking versions of Phphunit, you can make all tests pass.

- Patch A converts setExpectedExceptionRegExp calls to setExpectedException
- Patch B converts expectException calls to setExpectedException

Here are known working configurations:

| Moodle version | patch A | patch B | phpunit version | dbUnit version |
|----------------|---------|---------|-----------------|----------------|
| 2.7            |         |         |                 |                |
| 2.8            |         |         |                 |                |
| 2.9            |         |         |                 |                |
| 3.0            |         |         |                 |                |
| 3.1            |         |         |                 |                |
| 3.2            |         |         |                 |                |
| 3.3            |         |         |                 |                ||

Crafted by Catalyst IT
----------------------

This plugin was developed by Catalyst IT Australia:

https://www.catalyst-au.net/

![Catalyst IT](/pix/catalyst-logo.png?raw=true)

