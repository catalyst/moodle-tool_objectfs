<a href="https://travis-ci.org/catalyst/moodle-tool_objectfs">
<img src="https://travis-ci.org/catalyst/moodle-tool_objectfs.svg?branch=master">
</a>

# moodle-tool_objectfs

A remote object storage file system for Moodle. Intended to provide a plug-in that can be installed and configured to work with any supported remote object storage solution.

## Use cases
There are a number of different ways you can use this plug in. See [Recommended use case settings](#recommended-use-case-settings) for recommended settings for each one.

#### Hybrid file system
Files over a certain size threshold are synced up to remote storage and then removed locally. If they can't be read locally they will be read from remote storage. This will impact site performance.

#### Production master
A production server will sync all of it's files to remote storage but not remove them locally. All other supporting environments, E.g. staging and development, read from this remote storage.




## Currently supported object storage
- Amazon S3

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

## Remote object storage setup

### Amazon S3 bucket setup
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

## Recommended use case settings

#### Hybrid file system

#### Production master

## Backporting
Warning this is unsupported!

#### Moodle 2.7 only
1. Cherry pick [MDL-49627](https://tracker.moodle.org/browse/MDL-49627):
[MDL-49627 - part 1](https://github.com/moodle/moodle/commit/b7067f065e6ce8d7587039094259ace3e0804663),
[MDL-49627 - part 2](https://github.com/moodle/moodle/commit/2b53b13ff7b7cb98f81d5ef98214a91dedc124af)

2. Follow steps in section below.


#### Moodle 2.7, 2.8. 2.9. 3.0, 3.1, 3.2, 3.3
1. Cherry pick the file system API patch: [MDL-46375](https://tracker.moodle.org/browse/MDL-46375):
[MDL-46375 - part 1](https://github.com/moodle/moodle/commit/16a34ae1892014a6ca3055a95ac7310442529a6c),
[MDL-46375 - part 2](https://github.com/moodle/moodle/commit/0c03db6a32fb217756e091b691f1e885b608781b)
2. If you need tests to pass see [Test compatibility](test-compatibility)


#### Test compatibility
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




