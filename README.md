<a href="https://github.com/catalyst/moodle-tool_objectfs/actions?query=workflow%3A%22Run+all+tests%22">
<img src="https://github.com/catalyst/moodle-tool_objectfs/workflows/Run%20all%20tests/badge.svg">
</a>

# moodle-tool_objectfs

A remote object storage file system for Moodle. Intended to provide a plug-in that can be installed and configured to work with any supported remote object storage solution.
* [Use cases](#use-cases)
  * [Offloading large and old files to save money](#offloading-large-and-old-files-to-save-money)
  * [Sharing files across moodles to save disk](#sharing-files-across-moodles-to-save-disk)
  * [Sharing files across environments to save time](#sharing-files-across-environments-to-save-time)
  * [Sharing files with data washed environments](#sharing-files-with-data-washed-environments)
* [Installation](#installation)
* [Compatible object stores](#compatible-object-stores)
  * [Amazon S3](#amazon-s3)
  * [Minio.io S3](#minio-s3)
  * [Google gcs](#google-gcs)
  * [Azure Blob Storage](#azure-blob-storage)
  * [DigitalOcean Spaces](#digitalocean-spaces)
  * [Openstack Object Storage](#openstack-object-storage)
* [Moodle configuration](#moodle-configuration)
  * [General Settings](#general-settings)
  * [File Transfer settings](#file-transfer-settings)
  * [Pre-Signed URLs Settings](#pre-signed-urls-settings)
  * [Amazon S3 settings](#amazon-s3-settings)
  * [Minio.io S3 settings](#minio-s3-settings)
  * [Azure Blob Storage settings](#azure-blob-storage-settings)
  * [DigitalOcean Spaces settings](#digitalocean-spaces-settings)
* [Integration testing](#integration-testing)
* [Applying core patches](#applying-core-patches)
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

Using this plugin we can configure production to have full read write to the remote filesystem and store the vast bulk of content remotely. In this setup the latency and bandwidth isn't an issue as they are colocated. The local filedir on disk would only consist of small or fast churning files such as course backups. A refresh of the production data back to a staging environment can be much quicker now as we skip the sitedir clone completely and stage is simple configured with readonly access to the production filesystem. Any files it creates would only be written to it's local filesystem which can then be discarded when next refreshed.

### Sharing files with data washed environments

Often you want a sanitised version of the data for giving to developers or other 3rd parties to remove or obfuscate sensitive content. This plugin is designed to work in this scenario too where the 3rd party gets a 'cleaned' DB, and can still point to the production remote filesystem with readonly credentials. As they cannot query the filesystem directly and must know the content hash of any content in order to access a file, there is very low risk of them accessing sensitive content.

https://github.com/catalyst/moodle-local_datacleaner


## GDPR

This plugin is GDPR complient if you enable the deletion of remote objects.

## Branches

| Moodle version    | Totara version           | Branch                                                                                       | PHP  | MySQL   | PostgreSQL  |
|-------------------|--------------------------|----------------------------------------------------------------------------------------------|------|---------|-------------|
| Moodle 4.2+       |                          | [MOODLE_402_STABLE](https://github.com/catalyst/moodle-tool_objectfs/tree/MOODLE_402_STABLE) | 8.0+ | 8.0+    | 13+         |
| Moodle 3.10 - 4.1 |                          | [MOODLE_310_STABLE](https://github.com/catalyst/moodle-tool_objectfs/tree/MOODLE_310_STABLE) | 7.2+ | 5.7+    | 12+         |
| Moodle 3.3 - 3.9  | Totara 12                | [MOODLE_33_STABLE](https://github.com/catalyst/moodle-tool_objectfs/tree/MOODLE_33_STABLE)   | 7.1+ | 5.6+    | 9.5+        |
| Moodle 2.7 - 3.2  | Totara 2.7 - 2.9, 9 - 11 | [27-32-STABLE](https://github.com/catalyst/moodle-tool_objectfs/tree/27-32-STABLE)           | 5.5+ | 5.5.31+ | 9.1+        |


## Installation
1. If not on Moodle 3.3, backport the file system API. See [Backporting](#backporting)
2. Setup your remote object storage. See [Remote object storage setup](#amazon-s3)
3. Clone this repository into admin/tool/objectfs
4. Install one of the required SDK libraries for the storage file system that you will be using
    1. Clone [moodle-local_aws](https://github.com/catalyst/moodle-local_aws) into local/aws for S3 or DigitalOcean Spaces or Google Cloud, or
    2. Clone [moodle-local_azure_storage](https://github.com/catalyst/moodle-local_azure_storage) into local/azure_storage for Azure Blob Storage, or
    3. Clone [moodle-local_openstack](https://github.com/matt-catalyst/moodle-local_openstack.git) into local/openstack for openstack(swift) storage
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

* DigitalOcean Spaces
```php
$CFG->alternative_file_system_class = '\tool_objectfs\digitalocean_file_system';
```


* Openstack Object Storage (swift)
```php
$CFG->alternative_file_system_class = '\tool_objectfs\swift_file_system';
```

## Compatible object stores
Note: Not all object stores listed below are tested/in-use directly by Catalyst and some rely on community contributions. If you require commercial support/help please contact us privaately for details on our rates.

### Amazon S3

*Amazon S3 bucket setup*

- Create an Amazon S3 bucket.
- The AWS Users access policy should mirror the policy listed below.
- Replace 'bucketname' with the name of your S3 bucket.
- If you intend to allow deletion of objects in S3, Add 's3:DeleteObject' to the actions below.

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
### Minio S3

Setup for Minio.io bucket can be found on there website [here](https://min.io)


### Google GCS

*Google gcs setup*

- Create an gcs bucket.
- Go to the storage page, settings, interoperability. select `create a key for a service account`
  - choose create new account to create a service account
  - choose your new service account and press create key
  Use these for your secret and key options
- Replace 'bucketname' with the name of your S3 bucket.
- Add your service account as a member under the permissions tab for your new bucket with the `storage object admin` role
- set the bucket to use fine-grained access control
- You will need to set 'base_url' to https://storage.googleapis.com in your config

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

### DigitalOcean Spaces

*DigitalOcean Spaces bucket setup*

- Create an DigitalOcean Space.
- Currently DigitalOcean does not provide an ACL to their Spaces offering.

### Openstack Object Storage

*Openstack object storage container setup*

Create a dedicated user that does **not** have the 'Object Storage' role, and is then assign read and write permissions directly on the object storage container. This is to ensure least privileges.


- Create the container
```
openstack container create <container_name>
```
- Assign read permissions
```
swift post <container_name> -r '<project_name>:<storage_username>'
```
- Assign write permissions
```
swift post <container_name> -w '<project_name>:<storage_username>'
```

## Moodle configuration
Go to Site Administration -> Plugins -> Admin tools -> Object storage file system. Descriptions for the various settings are as follows:

### General Settings
- **Enable file transfer tasks**: Enable or disable the object file system tasks which move files between the filedir and remote object storage.
- **Maximum task runtime**: Background tasks handle the transfer of objects to and from remote object storage. This setting controls the maximum runtime for all object transfer related tasks.
- **Prefer remote objects**: If a file is stored both locally and in remote object storage, read from remote. This is setting is mainly for testing purposes and introduces overhead to check the location.

### File Transfer settings
These settings control the movement of files to and from object storage.

- **Minimum size threshold (bytes)**: Minimum size threshold in bytes for transferring objects to remote object storage. If objects are over this size they will be transferred.
- **Minimum age**: Minimum age that a object must exist on the local filedir before it will be considered for transfer.
- **Delete local objects**: Delete local objects once they are in remote object storage after the consistency delay.
- **Consistency delay**: How long an object must have existed after being transferred to remote object storage before they are a candidate for deletion locally.

### File System settings
- **Storage File System Selection**: The backend filesystem to be used. This is also used for the background transfer tasks when the main alternative_file_system_class variable is not set.

### Pre-Signed URLs Settings
- **Enable Pre-Signed URLs**: Enable redirect requesting content from external storage.
- **Pre-Signed URL expiration time**: The time after which the **Pre-Signed URL** should expire.
- **Minimum size for Pre-Signed URL (bytes)**: Minimum file size in bytes required to redirect requests to an external storage.
- **Pre-Signed URL whitelist**: Specify file extensions eligible to generate a **Pre-Signed URL**. If left empty requests will not be redirected to an external storage even if **Enable Pre-Signed URLs** is **ON**.  
- **Signing method**: Define the desired client to generate **Pre-Signed URLs**.
    * Options available:
        - **S3**. Inherits the settings from [Amazon S3 settings](#amazon-s3-settings)
        - **CloudFront**. It requires to create a [Cloudfront Distribution](https://docs.aws.amazon.com/AmazonCloudFront/latest/DeveloperGuide/distribution-web-creating-console.html). See [CLOUDFRONT.md](CLOUDFRONT.md) for more details.
- **DOMAIN (inc. https://)**: Domain name where the content requests will be redirected to.
- **Key_Pair ID from AWS**: Key to identify your trusted signers. [Creating CloudFront Key Pairs](https://docs.aws.amazon.com/AmazonCloudFront/latest/DeveloperGuide/private-content-trusted-signers.html#private-content-creating-cloudfront-key-pairs)
- **PRIVATE Key .pem**: Can be one of the following:
    * A file name with the pem extension e.g.: cloudfront.pem. The file should be located under the following path: **$CFG->dataroot . '/objectfs/'**
    * A PEM formatted string. e.g.:
```
      -----BEGIN RSA PRIVATE KEY-----
      S3O3BrpoUCwYTF5Vn9EQhkjsu8s...
      -----END RSA PRIVATE KEY-----
```
 
### Amazon S3 settings
S3 specific settings
- **Key**: AWS credential key.
- **Secret**: AWS credential secret.
- **Bucket**: S3 bucket name to store files in.
- **AWS region**: AWS API endpoint region to use.
- **Base URL**: useful for s3-compatible providers *eg* set to `https://storage.googleapis.com` for gcs
- **Key Prefix**: useful for adding a prefix for all data stored in bucket. Can be used to reuse the same CloudFront distribution for both Moodle itself and the pre-signed URLs files.

### Minio S3 settings
Use the AWS plugin for the objectfs addon.
- **Key**: Min.io Key
- **Secret**: Min.io Secret
- **Bucket**: S3 Bucket name
- **AWS region**: Doesn't matter
- **Base URL***: Your ip address of Min.io server or url. (If it's internal, see issue page [here](https://github.com/catalyst/moodle-tool_objectfs/issues/579).
- **Key Prefix**: useful for adding a prefix for all data stored in bucket. Can be used to reuse the same CloudFront distribution for both Moodle itself and the pre-signed URLs files.

### Azure Blob Storage settings
Azure Blob Storage specific settings
- **Storage account**: Storage account name.
- **Container name**: Name of the container that will be used.
- **Shared Access Signature**: A shared access signature that is signed to use the container. Recommended with Read and Write access only.

### DigitalOcean Spaces settings
DigitalOcean Spaces specific settings
- **Key**: DO Spaces credential key.
- **Secret**: DO Spaces secret credential secret.
- **Space**: DigitalOcean space name to store files in.
- **Region**: DigitalOcean Spaces API endpoint region to use.


### Openstack Object Storage settings
Openstack Object Storage settings
- **Username**: Storage account username
- **Password**: Storage account password
- **Authentication URL**: URL to openstack auth API
- **Region**: Openstack region
- **Tenant Name**: Openstack tenant name
- **Project ID**: Openstack project ID
- **Container**: Name of the storage container

## Integration testing
Objectfs supports integration testing with S3, Azure and Swift storages. Please refer to [TESTING.md](TESTING.md) for more detail.

## Applying core patches

This plugin requires various trackers to be backported to maintain the plugin functionality.

| Moodle version   | Totara version     | Branch       | Mandatory patches | Pre-signed URLs |
|------------------|--------------------|--------------|-------------------|-----------------|
| Moodle 3.9       |                    | master       |                   |                 |
| Moodle 3.8       |                    | master       | MDL-58281         | MDL-68342       |
| Moodle 3.4 - 3.7 |                    | master       | MDL-58281         | MDL-68342,<br>MDL-66304 |
| Moodle 3.3       | Totara 12          | master       | MDL-58281         | MDL-68342,<br>MDL-53240,<br>MDL-66304 |
| Moodle 3.2       | Totara 11          | 27-32-STABLE | MDL-58281,<br>MDL-46375,<br>MDL-58068,<br>MDL-58684,<br>MDL-58297 | MDL-68342,<br>MDL-53240,<br>MDL-66304 |
| Moodle 2.9 - 3.1 | Totara 2.9, 9 - 10 | 27-32-STABLE | MDL-58281,<br>MDL-46375,<br>MDL-58068,<br>MDL-58684,<br>MDL-58297,<br>MDL-55071 | MDL-68342,<br>MDL-53240,<br>MDL-66304 |
| Moodle 2.7 - 2.8 | Totara 2.7 - 2.8   | 27-32-STABLE | MDL-58281,<br>MDL-46375,<br>MDL-58068,<br>MDL-49627,<br>MDL-58684,<br>MDL-58297<br>MDL-55071,<br>MDL-42616 | MDL-68342,<br>MDL-53240,<br>MDL-66304 |

#### Moodle 3.9:
TBA

#### Moodle 3.8:
Apply the patch:
<pre>
git am --whitespace=nowarn < admin/tool/objectfs/patch/core38.diff
</pre>
The patch was created with following commands: 
<pre>
// Cherry-pick MDL-58281
git cherry-pick 1fef1de5922f7ea130e4994b3453610079874b63

// Cherry-pick MDL-68342
git cherry-pick 5bf5a7aaebabff669a674f19a4ec33cbca24f515

// Create the patch
git format-patch MOODLE_38_STABLE --stdout > core38.diff
</pre>

#### Moodle 3.4 - 3.7:
TBA

#### Moodle 3.3 and Totara 12:
Apply the patch:
<pre>
git am --whitespace=nowarn < admin/tool/objectfs/patch/core33.diff
</pre>
The patch was created with following commands:
<pre>
// Cherry-pick MDL-53240
git cherry-pick 6c4a5fdf88ac8ad88c4e86cf9b54d2b55bf2fd58
git cherry-pick e3ad9db6b67aa378b1787497991a4422f57a6a3d
git cherry-pick 8cf36e9c81a704c8dee90d0fef9402aec0eaf80e
git cherry-pick 97bb4f755e5f5c8e488ecc7ad7cc932c4e294226

// Cherry-pick MDL-66304
git cherry-pick 1a159252405e85394d241922a5244309e9ad14f4

// Cherry-pick MDL-68342
git cherry-pick 5bf5a7aaebabff669a674f19a4ec33cbca24f515

// Cherry-pick MDL-58281
git cherry-pick 1fef1de5922f7ea130e4994b3453610079874b63

// Create the patch
git format-patch MOODLE_33_STABLE --stdout > core33.diff
</pre>

#### Moodle 3.2 and Totara 11:
Apply the patch:
<pre>
git am --whitespace=nowarn < admin/tool/objectfs/patch/core32.diff
</pre>
The patch was created with following commands: 
<pre>
// Cherry-pick MDL-46375
git cherry-pick 16a34ae1892014a6ca3055a95ac7310442529a6c
git cherry-pick 0c03db6a32fb217756e091b691f1e885b608781b

// Cherry-pick MDL-58068
git cherry-pick db4b59fa03049992842b47c99ef8e80b41c8093d

// TBA
// Revert the changes for MDL-35290 and cherry-pick them from 3.3 instead
// git revert 100a53119a719a1a5564fedc3e2db4eb70d19857 655b4543662f1b49978c1176e68fffad6286b7b4
// git cherry-pick 67fa4b55b95ea179f68ae8f5f2af84adf18f5546

// Cherry-pick MDL-58684
// TBA
// git cherry-pick 5529b4701aa52caf30a25052ba90aaa7b7dc0ef7
// WARNING: This commit has a DB upgrade. Change the version numbers to appropriately match your version of moodle.
// git cherry-pick e927581a50dbbf39b22ab9a49e0e316fe0cc83f1

// Cherry-pick MDL-58297, MDL-58281, MDL-68342, MDL-53240, MDL-66304
// TBA

// Create the patch
git format-patch MOODLE_32_STABLE --stdout > core32.diff
</pre>

#### Moodle 2.9 - 3.1 and Totara 2.9, 9 - 10:
Apply the patch for you Moodle version:
<pre>
git am --whitespace=nowarn < admin/tool/objectfs/patch/core31.diff
git am --whitespace=nowarn < admin/tool/objectfs/patch/core30.diff
git am --whitespace=nowarn < admin/tool/objectfs/patch/core29.diff
</pre>
The patch was created with following commands: 
<pre>
// Cherry-pick MDL-46375
git cherry-pick 16a34ae1892014a6ca3055a95ac7310442529a6c
git cherry-pick 0c03db6a32fb217756e091b691f1e885b608781b

// Cherry-pick MDL-58068
git cherry-pick db4b59fa03049992842b47c99ef8e80b41c8093d

// Cherry-pick MDL-53240
git cherry-pick 6c4a5fdf88ac8ad88c4e86cf9b54d2b55bf2fd58
git cherry-pick e3ad9db6b67aa378b1787497991a4422f57a6a3d
git cherry-pick 8cf36e9c81a704c8dee90d0fef9402aec0eaf80e
git cherry-pick 97bb4f755e5f5c8e488ecc7ad7cc932c4e294226

// Cherry-pick MDL-58684
// TBA
// git cherry-pick 5529b4701aa52caf30a25052ba90aaa7b7dc0ef7
// WARNING: This commit has a DB upgrade. Change the version numbers to appropriately match your version of moodle.
// git cherry-pick e927581a50dbbf39b22ab9a49e0e316fe0cc83f1

// Cherry-pick MDL-58297, MDL-58281, MDL-68342, MDL-66304, MDL-55071
// TBA

// Create the patch
git format-patch MOODLE_31_STABLE --stdout > core31.diff
git format-patch MOODLE_30_STABLE --stdout > core30.diff
git format-patch MOODLE_29_STABLE --stdout > core29.diff
</pre>

#### Moodle 2.7 - 2.8 and Totara 2.7 - 2.8:
Apply the patch for you Moodle version:
<pre>
git am --whitespace=nowarn < admin/tool/objectfs/patch/core28.diff
git am --whitespace=nowarn < admin/tool/objectfs/patch/core27.diff
</pre>
The patch was created with following commands: 
<pre>
// Cherry-pick MDL-49627
git cherry-pick b7067f065e6ce8d7587039094259ace3e0804663
git cherry-pick 2b53b13ff7b7cb98f81d5ef98214a91dedc124af

// Cherry-pick MDL-46375
git cherry-pick 16a34ae1892014a6ca3055a95ac7310442529a6c
git cherry-pick 0c03db6a32fb217756e091b691f1e885b608781b

// Cherry-pick MDL-58068
git cherry-pick db4b59fa03049992842b47c99ef8e80b41c8093d

// Cherry-pick MDL-53240
git cherry-pick 6c4a5fdf88ac8ad88c4e86cf9b54d2b55bf2fd58
git cherry-pick e3ad9db6b67aa378b1787497991a4422f57a6a3d
git cherry-pick 8cf36e9c81a704c8dee90d0fef9402aec0eaf80e
git cherry-pick 97bb4f755e5f5c8e488ecc7ad7cc932c4e294226

// Cherry-pick MDL-42616
git cherry-pick 91fed57a4e4005589425d701c6a888130763ca8a
git cherry-pick d3fd2be7aab748b8b13c1c47b89a37bfd0e03256
git cherry-pick ae46ca5fcc8823c446fba6b275daa02f5c8a7973

// Cherry-pick MDL-58684
// TBA
// git cherry-pick 5529b4701aa52caf30a25052ba90aaa7b7dc0ef7
// WARNING: This commit has a DB upgrade. Change the version numbers to appropriately match your version of moodle.
// git cherry-pick e927581a50dbbf39b22ab9a49e0e316fe0cc83f1

// Cherry-pick MDL-58297, MDL-58281, MDL-68342, MDL-66304, MDL-55071
// TBA

// Create the patch
git format-patch MOODLE_28_STABLE --stdout > core28.diff
git format-patch MOODLE_27_STABLE --stdout > core27.diff
</pre>

TODO: Watch and add steps for these trackers when they are integrated: MDL-57971

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
