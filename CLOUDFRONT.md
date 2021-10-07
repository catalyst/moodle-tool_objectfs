### Create AWS bucket
1. Login to AWS console https://aws.amazon.com/console/
2. Navigate to _Services -> S3_.
3. Click _Create bucket_.
4. Fill out bucket name, region and click _Create bucket_.
5. Navigate to _My Security Credentials_.
6. In the _Access keys_ section click on the _Create New Access Key_ button.
7. Write down your bucket name, region, key and secret.
8. Edit the bucket again.
9. Set _Default encryption_ to _Enabled_ with _Amazon S3 master-key (SSE-S3)_ server-side encryption.
10. Set the following as _Cross-origin resource sharing (CORS)_:
```
[
    {
        "AllowedHeaders": [
            "*"
        ],
        "AllowedMethods": [
            "GET",
            "HEAD"
        ],
        "AllowedOrigins": [
            "*"
        ],
        "ExposeHeaders": [],
        "MaxAgeSeconds": 3000
    }
]
```

### Configure Objectfs
1. Run the following commands via CLI:
```
php admin/cli/cfg.php --component=tool_objectfs --name=enabletasks --set=1
php admin/cli/cfg.php --component=tool_objectfs --name=deletelocal --set=1
php admin/cli/cfg.php --component=tool_objectfs --name=consistencydelay --set=0
php admin/cli/cfg.php --component=tool_objectfs --name=sizethreshold --set=0
php admin/cli/cfg.php --component=tool_objectfs --name=minimumage --set=0
php admin/cli/cfg.php --component=tool_objectfs --name=filesystem --set='\tool_objectfs\s3_file_system'
php admin/cli/cfg.php --component=tool_objectfs --name=s3_key --set='your key'
php admin/cli/cfg.php --component=tool_objectfs --name=s3_secret --set='your secret'
php admin/cli/cfg.php --component=tool_objectfs --name=s3_bucket --set='your bucket'
php admin/cli/cfg.php --component=tool_objectfs --name=s3_region --set='your region'
```
2. Put the following line into your _config.php_:
```
$CFG->alternative_file_system_class = '\tool_objectfs\s3_file_system';
```
3. Access the _/admin/settings.php?section=tool_objectfs_settings_ page.
4. Confirm, that there is a green notification message _Could establish connection to the external object storage._ under the _Amazon S3 Settings_ section.
5. Run the fllowing scheduled tasks:
```
php admin/cli/scheduled_task.php --execute='\tool_objectfs\task\check_objects_location'
php admin/cli/scheduled_task.php --execute='\tool_objectfs\task\push_objects_to_storage'
php admin/cli/scheduled_task.php --execute='\tool_objectfs\task\delete_local_objects'
php admin/cli/scheduled_task.php --execute='\tool_objectfs\task\generate_status_report'
```
6. Access the _/admin/tool/objectfs/object_status.php_ page.
7. Confirm, that all files have been moved to the external storage: _Marked as only in filedir_ and _Duplicated in filedir and external storage_ should be 0.

### Create CloudFront distribution
1. Navigate to [https://console.aws.amazon.com/cloudfront/v3/home?region=ap-southeast-2#/welcome].
2. Click on _Create a CloudFront distribution_.
3. Choose your Amazon S3 bucket from _Origin domain_ dropdown menu.
4. _S3 bucket access_: Choose _Yes use OAI (bucket can restrict access to only CloudFront)_ and click _Create new OAI_.
5. _S3 bucket access -> Bucket policy_: Choose _Yes, update the bucket policy_.
6. _Viewer protocol policy_: Choose _Redirect HTTP to HTTPS_.
7. _Allowed HTTP methods_: Choose _GET, HEAD, OPTIONS_ and tick _OPTIONS_ under _Cache HTTP methods_.
8. _Restrict viewer access_: Choose _Yes -> Trusted signer -> Self_.
9. _Cache key and origin requests_: Choose _Legacy cache settings_.
10. _Legacy cache settings -> Headers_: Choose _Include the following headers_ and add _Origin_, _Access-Control-Request-Method_, _Access-Control-Request-Headers_ headers from the dropdown menu.
11. _Legacy cache settings -> Query strings_: Choose _All_.
12. Click _Create distribution_.
13. Navigate to [https://console.aws.amazon.com/cloudfront/v3/home?region=ap-southeast-2#/distributions].
14. Confirm, that _Status_ is _Enabled_ and _Last modified_ is changed from _Deploying_ to the date the distribution was created.
15. Open your distribution.
16. Write down _Distribution domain name_ (with https://).
> Note: If you have already setup Moodle behind a CloudFront distribution, it is also possible to use that same CloudFront distribution to serve files from objectfs. In this scenario, a specific prefix in the URL path directs traffic to the S3 Bucket (moodle.domain/objectfs/ for example). To achieve that, use the key_prefix option to add a prefix on your Bucket, and configure a second Origin on your existing CloudFront distribution that points to your Bucket. Setup a Behavior that uses that new Origin with the same prefix as the one you used as key_prefix in your Bucket. Follow all other instructions.

### Generate keys
1. Make a directory _$CFG->dataroot . '/objectfs/'_.
2. Make it readable and writable:
```
chmod 777 objectfs
```
3. Generate an RSA key pair with a length of 2048 bits:
```
cd objectfs/
openssl genrsa -out cloudfront.pem 2048
chmod 777 cloudfront.pem
```
4. Extract the public key:
```
openssl rsa -pubout -in cloudfront.pem -out public_key.pem
```
5. Navigate to [https://console.aws.amazon.com/cloudfront/v3/home#/distributions].
6. In the navigation menu, choose _Public keys_.
7. Click _Create public key_.
8. Enter key name.
9. Enter key value. Use the following command to get the public key:
```
cat public_key.pem
```
10. Click _Create public key_.
11. Write down key ID from the [https://console.aws.amazon.com/cloudfront/v3/home#/publickey] page.

### Configure CloudFront signing method in Objectfs:
1. Run the following commands from the CLI to configure Objectfs:
```
php admin/cli/cfg.php --component=tool_objectfs --name=enablepresignedurls --set=1
php admin/cli/cfg.php --component=tool_objectfs --name=expirationtime --set=172800
php admin/cli/cfg.php --component=tool_objectfs --name=presignedminfilesize --set=0
php admin/cli/cfg.php --component=tool_objectfs --name=signingwhitelist --set='*'
php admin/cli/cfg.php --component=tool_objectfs --name=signingmethod --set='cf'
php admin/cli/cfg.php --component=tool_objectfs --name=cloudfrontresourcedomain --set='your cloudfrom domain'
php admin/cli/cfg.php --component=tool_objectfs --name=cloudfrontkeypairid --set='your key pair id'
php admin/cli/cfg.php --component=tool_objectfs --name=cloudfrontprivatekey --set='cloudfront.pem'
```
2. Please note that _cloudfrontprivatekey_ setting can can be one of the following:
* a file name with the pem extension (described in this wiki), or
* a PEM formatted string, eg:
```
-----BEGIN RSA PRIVATE KEY-----
MIIEpAIBAAKCAQEAynfONnizsVKXwuoXXWZC948QFsZme3zXUJ7PDrd4fKBpDCPr
...
TPdsThtG51qIzZxYw4jlle2jCArTEta9meJRwpU9X32omvHLdENBnw==
-----END RSA PRIVATE KEY-----
```
3. Open Dev Tool Network tab and navigate to the _/admin/tool/objectfs/presignedurl_tests.php_ page.
4. Confirm, that file requests like _/pluginfile.php/1/tool_objectfs/settings/0/testvideo.mp4_ get redirected to pre-signed CloudFront URL (HTTP status 303).
5. Confirm, that requests to pre-signed CloudFront URL return requested data (HTTP status 200).

### A fix for [MDL-70323](https://tracker.moodle.org/browse/MDL-70323) and [mod_hvp](https://github.com/h5p/h5p-php-library/pull/90)
1. Put the following lines into your _config.php_ to make sure H5P activities are displayed correctly:
```
$CFG->h5pcrossorigin = 'anonymous';
$CFG->mod_hvp_crossorigin = 'anonymous';
```
