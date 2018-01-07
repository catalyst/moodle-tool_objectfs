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
  * [Azure Blob Storage](#azure-blob-storage)
* [Moodle configuration](#moodle-configuration)
  * [General Settings](#general-settings)
  * [File Transfer settings](#file-transfer-settings)
  * [Amazon S3 settings](#amazon-s3-settings)
  * [Azure Blob Storage settings](#azure-blob-storage-settings)
* [Backporting](#backporting)
* [Crafted by Catalyst IT](#crafted-by-catalyst-it)
* [Contributing and support](#contributing-and-support)

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
2. Setup your remote object storage. See [Remote object storage setup](#amazon-s3)
3. Clone this repository into admin/tool/objectfs
4. Install one of the required SDK libraries for the storage file system that you will be using
    1. Clone [moodle-local_aws](https://github.com/catalyst/moodle-local_aws) into local/aws, or
    2. Clone [moodle-local_azure_storage](https://github.com/catalyst/moodle-local_azure_storage) into local/azure_storage
5. Install the plugins through the moodle GUI.
6. Configure the plugin. See [Moodle configuration](#moodle-configuration)
7. Place of the following lines inside your Moodle config.php:

* Amazon S3
```php
$CFG->alternative_file_system_class = '\tool_objectfs\s3_file_system';
```

* Azure Blob Storage
```php
$CFG->alternative_file_system_class = '\tool_objectfs\azure_file_system';
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
        "s3:GetObject"
      ],
      "Resource": ["arn:aws:s3:::bucketname/*"]
    }
  ]
}
```

### Azure Blob Storage

*Azure Storage container guide with the CLI*

It is possible to install the Azure CLI locally to administer the storage account. [The Azure CLI can be obtained here.](https://docs.microsoft.com/en-us/cli/azure/install-azure-cli?view=azure-cli-latest)

Visit the [Online Azure Portal](https://portal.azure.com) or use the Azure CLI to obtain the storage account keys. These keys are used to setup the container, configure an access policy and acquire a [Shared Access Signature](https://docs.microsoft.com/en-us/azure/storage/common/storage-dotnet-shared-access-signature-part-1) that has Read and Write capabilities on the container.

It will be assumed at this point that a [resource group](https://docs.microsoft.com/en-us/azure/azure-resource-manager/resource-group-overview) and [blob storage account](https://docs.microsoft.com/en-us/azure/storage/common/storage-introduction) exists.

- Obtain the account keys.
```
az login

az storage account keys list \
  --resource-group <resource_group_name> \
  --account-name <storage_account_name>
```

- Create a private container in a storage account.
```
az storage container create \
    --name <container_name> \
    --account-name <storage_account_name> \
    --account-key <storage_account_key> \
    --public-access off \
    --fail-on-exist
```

- Create a stored access policy on the containing object.
```
az storage container policy create \
    --account-name <storage_account_name> \
    --account-key <storage_account_key> \
    --container-name <container_name> \
    --name <policy_name> \
    --start <YYYY-MM-DD> \
    --expiry <YYYY-MM-DD> \
    --permissions rw

# Start and Expiry are optional arguments.
```

- Generates a shared access signature for the container. This is associated with a policy.
```
az storage container generate-sas \
    --account-name <storage_account_name> \
    --account-key <storage_account_key> \
    --name <container_name> \
    --policy <policy_name> \
    --output tsv
```

- If you wish to revoke access to the container, remove the policy which will invalidate the SAS.
```
az storage container policy delete \
    --account-name <storage_account_name> \
    --account-key <storage_account_key> \
    --container-name <container_name>
    --name <policy_name>
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

### File System settings
- **Storage File System Selection**: The backend filesystem to be used. This is also used for the background transfer tasks when the main alternative_file_system_class variable is not set.

### Amazon S3 settings
S3 specific settings
- **Key**: AWS credential key.
- **Secret**: AWS credential secret.
- **Bucket**: S3 bucket name to store files in.
- **AWS region**: AWS API endpoint region to use.

### Azure Blob Storage settings
Azure Blob Storage specific settings
- **Storage account**: Storage account name.
- **Container name**: Name of the container that will be used.
- **Shared Access Signature**: A shared access signature that is signed to use the container. Recommended with Read and Write access only.

## Backporting

If you are on an older moodle then you can backport the necessary API's in order to support this plugin. Use with caution!

### Backport the File System API
#### Moodle 2.6 only
1. Cherry pick [MDL-44510](https://tracker.moodle.org/browse/MDL-44510):
<pre>
git remote add upstream git@github.com:moodle/moodle.git
git fetch upstream
git cherry-pick 54f1423ecc1f5372ca452ab14aeab16489ce1e6f
// Solve conflicts as needed.
</pre>

2. Back port the Moodle lock API. A patch has been prepared. Note: this patch does not include installation of the lock tables - this is handled by the plugin itself to avoid hacking core update.php and version.
<pre>
git remote add fsapi git@github.com:kenneth-hendricks/moodle-fs-api.git
git fetch fsapi
git cherry-pick b66908597636a0389154aaa86172b63a2570dd31
// Solve conflicts as needed.
</pre>

3. Follow steps in sections below.

#### Moodle 2.6 - 2.8 only
1. Cherry pick [MDL-49627](https://tracker.moodle.org/browse/MDL-49627):
<pre>
git remote add upstream git@github.com:moodle/moodle.git
git fetch upstream
git cherry-pick 47d3338..2b53b13
// Solve conflicts and git cherry-pick --continue as needed.
</pre>

2. Follow steps in section below.

#### Moodle 2.6 - 3.2
1. Cherry pick the file system API patch: [MDL-46375](https://tracker.moodle.org/browse/MDL-46375):
<pre>
git remote add upstream git@github.com:moodle/moodle.git
git fetch upstream
git cherry-pick 846d899..0c03db6
// Solve conflicts and git cherry-pick --continue as needed.
</pre>

2. Moodle 3.2 only: revert the changes for MDL-35290 and cherry-pick them from 3.3 instead:
<pre>
git revert 100a53119a719a1a5564fedc3e2db4eb70d19857 655b4543662f1b49978c1176e68fffad6286b7b4
git cherry-pick 67fa4b55b95ea179f68ae8f5f2af84adf18f5546
// Solve conflicts and git cherry-pick --continue as needed.
</pre>

3. If you need tests to pass see PHPUnit test compatibility below .


### Update the backported File System API
Since it was first created there have been a number of bug fixes to the File System API. These should also be back ported.

TODO: Add watch and add steps for these trackers: MDL-58297, MDL-58281, MDL-58068, MDL-57971

* [MDL-58684](https://tracker.moodle.org/browse/MDL-58684)
<pre>
git remote add upstream git@github.com:moodle/moodle.git
git fetch upstream
git cherry-pick 5529b4701aa52caf30a25052ba90aaa7b7dc0ef7
// WARNING: This commit has a DB upgrade. Change the version numbers to appropriately match your version of moodle.
git cherry-pick e927581a50dbbf39b22ab9a49e0e316fe0cc83f1
</pre>


### PHPUnit test compatibility
The file system API patch introduces tests that use:
- setExpectedExceptionRegExp() which needs phpunit 4.3
- setExpectedException() which needs phpunit 5.2 which needs needs php 5.6 (Ubuntu 14.04 runs 5.5.9)
- exception strings that have have changed between Moodle versions.


By cherry-picking combination of patches to the new file system API tests and tweaking versions of Phphunit in composer.json, you can make all tests pass.

- [Patch A](https://github.com/kenneth-hendricks/moodle-fs-api/commit/175bd1fd01a0fbf11ac6370e04347c05bcbba62f) converts setExpectedExceptionRegExp calls to setExpectedException
- [Patch B](https://github.com/kenneth-hendricks/moodle-fs-api/commit/b2c75c4a3c167cb6e9fa802025e77e87458ed32b) converts expectException calls to setExpectedException
- [Patch C](https://github.com/kenneth-hendricks/moodle-fs-api/commit/314486b3379fad4617937495ddf29240cdc7a069) Modifies an expected exception message

Apply them as follows:
<pre>
git remote add fsapi git@github.com:kenneth-hendricks/moodle-fs-api.git
git fetch fsapi

//Patch A
git cherry-pick 175bd1fd01a0fbf11ac6370e04347c05bcbba62f

//Patch B
git cherry-pick b2c75c4a3c167cb6e9fa802025e77e87458ed32b

//Patch C
git cherry-pick 314486b3379fad4617937495ddf29240cdc7a069

</pre>

Here are known working configurations:

| Moodle version | Patch A | Patch B | Patch C | composer.json  |
|----------------|---------|---------|---------|----------------|
| [2.7](https://github.com/kenneth-hendricks/moodle-fs-api/tree/MOODLE_27_STABLE_FSAPI)            |    Yes     |   Yes      |      Yes   |     [composer.json](https://github.com/kenneth-hendricks/moodle-fs-api/blob/MOODLE_27_STABLE_FSAPI/composer.json)           |
| [2.8](https://github.com/kenneth-hendricks/moodle-fs-api/tree/MOODLE_28_STABLE_FSAPI)            |    Yes     |   Yes      |      Yes   |     [composer.json](https://github.com/kenneth-hendricks/moodle-fs-api/blob/MOODLE_28_STABLE_FSAPI/composer.json)           |
| [2.9](https://github.com/kenneth-hendricks/moodle-fs-api/tree/MOODLE_29_STABLE_FSAPI)            |    Yes     |   Yes      |      No   |     [composer.json](https://github.com/kenneth-hendricks/moodle-fs-api/blob/MOODLE_29_STABLE_FSAPI/composer.json)           |
| [3.0](https://github.com/kenneth-hendricks/moodle-fs-api/tree/MOODLE_30_STABLE_FSAPI)            |    No     |   Yes      |      No   |     [composer.json](https://github.com/kenneth-hendricks/moodle-fs-api/blob/MOODLE_30_STABLE_FSAPI/composer.json)           |
| [3.1](https://github.com/kenneth-hendricks/moodle-fs-api/tree/MOODLE_31_STABLE_FSAPI)            |    No     |   Yes      |      No   |     [composer.json](https://github.com/kenneth-hendricks/moodle-fs-api/blob/MOODLE_31_STABLE_FSAPI/composer.json)           |
| [3.2](https://github.com/kenneth-hendricks/moodle-fs-api/tree/MOODLE_32_STABLE_FSAPI)            |    No     |   No      |      No   |     [composer.json](https://github.com/kenneth-hendricks/moodle-fs-api/blob/MOODLE_32_STABLE_FSAPI/composer.json)           |


Contributing and support
------------------------

Issues, and pull requests using github are welcome and encouraged!

https://github.com/catalyst/moodle-tool_objectfs/issues

If you would like commercial support or would like to sponsor additional improvements
to this plugin please contact us:

https://www.catalyst-au.net/contact-us


Warm thanks
-----------

Thanks to Microsoft for sponsoring the Azure Storage implementation.

![Microsoft](/pix/Microsoft-logo_rgb_c-gray.png?raw=true)


Crafted by Catalyst IT
----------------------

This plugin was developed by Catalyst IT Australia:

https://www.catalyst-au.net/

![Catalyst IT](/pix/catalyst-logo.png?raw=true)
