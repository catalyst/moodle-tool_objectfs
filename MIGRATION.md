# Migration guides

## Migrating from local_azure_storage to local_azureblobstorage

Since March 2024, Microsoft officially discontinued support for the PHP SDK for Azure storage. This means the `local_azure_storage` plugin which is a wrapper of the SDK will no longer be updated, and is already out of date with newer php versions.

The plugin `local_azureblobstorage` was created to replace this, with a simpler and cleaner API for interacting with the Azure blob storage service via REST APIs. Objectfs has been updated with a new client handler class to enable you to cut over to the new storage system as easily as possible.

This new library is only supported in higher PHP versions.

### Steps
1. If you are on Moodle 4.2, ensure you have updated the previous `local_azure_storage` to the `MOODLE_42_STABLE` branch. This fixes some fatal errors caused by Guzzle namespace conflicts.
2. Install `local_azureblobstorage` https://github.com/catalyst/moodle-local_azureblobstorage
3. In the objectfs settings, change the `filesystem` config variable to `\tool_objectfs\azure_blob_storage_file_system` and save. ObjectFS will now be using the new API to communicate with Azure. You do not need to enter new credentials, the credentials are shared with the old client.
4. Test and ensure the site works as expected.
5. If you encounter any issues and wish to revert back, simply change the `filesystem` configuration back to the old client. This will immediately begin to use the old libraries again.
6. Once you are happy, simply uninstall the `local_azure_storage` plugin. The migration is now complete.