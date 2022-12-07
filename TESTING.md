# Integration testing
Objectfs supports integration testing with S3, Azure and Swift storage ([Test client initialisation](https://github.com/catalyst/moodle-tool_objectfs/blob/master/tests/classes/test_file_system.php)).

To run integration tests follow steps below:
1. Install `tool_objectfs` plugin and required SDK libraries.
2. Initialise php unit environment:
   `php admin/tool/phpunit/cli/init.php`
3. Set integration credentials in `config.php` for one of the supported storage:
* S3:
```php
$CFG->phpunit_objectfs_s3_integration_test_credentials = array(
    's3_key' => 'Your key',
    's3_secret' => 'Your secret',
    's3_bucket' => 'Your bucket',
    's3_region' => 'Your region',
);
```
* Azure:
```php
$CFG->phpunit_objectfs_azure_integration_test_credentials = array(
    'azure_accountname' => 'Your account name',
    'azure_container' => 'Your container',
    'azure_sastoken' => 'Your sas token',
);
```
* Swift:
```php
$CFG->phpunit_objectfs_swift_integration_test_credentials = array(
    'openstack_authurl' => 'Your auth URL',
    'openstack_region' => 'Your region',
    'openstack_container' => 'Your container',
    'openstack_username' => 'Your username',
    'openstack_password' => 'Your password',
    'openstack_tenantname' => 'Your tenantname',
    'openstack_projectid' => 'Your project ID',
);
```
* Run Objectfs tests:
```php
vendor/bin/phpunit -c admin/tool/objectfs/
```
* To run tests for core subsystems or other plugins that make use of the filesystem against Objectfs, add the following to config.php:
```php
$CFG->alternative_file_system_class = '\tool_objectfs\tests\test_file_system';
```
