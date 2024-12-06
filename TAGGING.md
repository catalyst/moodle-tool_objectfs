# Tagging
Tagging allows extra metadata about your files to be send to the external object store. These sources are defined in code, and currently cannot be configured on/off from the UI.

Currently, this is only implemented for the S3 file system client. 
**Tagging vs metadata**

Note object tags are different from object metadata.

Object metadata is immutable, and attached to the object on upload. With metadata, if you wish to update it (for example during a migration, or the sources changed), you have to copy the object with the new metadata, and delete the old object. This is not ideal, since deletion is optional in objectfs.

Object tags are more suitable, since their permissions can be managed separately (e.g. a client can be allowed to modify tags, but not delete objects).

## File system setup
### S3
[See the S3 docs for more information about tagging](https://docs.aws.amazon.com/AmazonS3/latest/userguide/object-tagging.html).

You must allow `s3:GetObjectTagging` and `s3:PutObjectTagging` permission to the objectfs client.

## Sources
The following sources are implemented currently:
### Environment
What environment the file was uploaded in. Configure the environment using `taggingenvironment` in the objectfs plugin settings.

This tag is also used by objectfs to determine if tags can be overwritten. See [Multiple environments setup](#multiple-environments-setup) for more information.

### Location
Either `orphan` if the file no longer exists in the `files` table in Moodle, otherwise `active`.

## Multiple environments setup
This feature is designed to work in situations where multiple environments (e.g. prod, staging) points to the same bucket, however, some setup is needed:

1. Turn off `overwriteobjecttags` in every environment except the production environment.
2. Configure `taggingenvironment` to be unique for all environments.

By doing the above two steps, it will allow the production environment to always set its own tags, even if a file was first uploaded to staging and then to production.

Lower environments can still update tags, but only if the `environment` matches theirs. This allows staging to manage object tags on objects only it knows about, but as soon as the file is uploaded from production (and therefore have it's environment tag replaced with `prod`), staging will no longer touch it.

## Migration
Only new objects uploaded after enabling this feature will have tags added. To backfill tags for previously uploaded objects, you must do the following:

- Manually run `trigger_update_object_tags` scheduled task from the UI, which queues a `update_object_tags` adhoc task that will process all objects marked as needing sync.
or
- Call the CLI to execute a `update_object_tags` adhoc task manually.

You may need to update the DB to mark objects tag sync status as needing sync if the object has previously been synced before.
## Reporting
There is an additional graph added to the object summary report showing the tag value combinations and counts of each.

Note, this is only for files that have been uploaded from the respective environment, and may not be consistent for environments where `overwriteobjecttags` is disabled (because the site does not know if a file was overwritten in the external store by another client).

## For developers

### Adding a new source
Note the rules about sources:
- Identifier must be < 32 chars long.
- Value must be < 128 chars long.

While external providers allow longer key/values, we intentionally limit it to reserve space for future use. These limits may change in the future as the feature matures.

To add a new source:
- Implement `tag_source`
- Add to the `tag_manager` class
- As part of an upgrade step, mark all objects `tagsyncstatus` to needing sync (using `tag_manager` class, or manually in the DB)
- As part of an upgrade step, queue a `update_object_tags` adhoc task to process the tag migration.