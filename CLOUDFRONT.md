## Implementing CloudFront CDN in-front of ObjectFS S3 Bucket
The following steps outline how to create an S3 bucket for ObjectFS, configure moodle to use this, 
and then how to implement the CloudFront CDN (Content Delivery Network) to securely sit infront of the 
S3 Bucket, so that content delivery maybe off-loaded from the moodle servers to the CDN.  This will 
typically result in faster access for users to content due do caching by the CDN, and less load on the
moodle servers.

The following steps implement the following high level objectives:
1. Grant the Cloudfront Distribution access to the S3 bucket for ObjectFS
 
  - existing steps in document are for "Legacy access identies"
		"Use a CloufFront origin access identity (OAI) to access the S3 Bucket"

  - use "Origin access control settings (recommended)" 
    "Bucket can restrict acess to only CloudFront."
    This configuration has been tested, and also works. This blog post outlines the advantages of the newer option:
    https://aws.amazon.com/blogs/networking-and-content-delivery/amazon-cloudfront-introduces-origin-access-control-oac/

  - Update S3 Bucket Policy (this step is required for either legacy OAI or Origin access control.
	"Policy must allow access to CloudFront IAM service principle role". (policy auto generated)


2. Restrict viewer access (CloudFront Distribution) to signed requests (Trusted Key Groups)

	- Generate key pair
	- (Cloudfront) configure Trusted Key Groups (public key)
	- (Cloudfront) Restrict view access
	- (Moodle) Configure to generate signed URLs (private key)

3. Setup CORS security (response header policy) for the Cloudfront distribution


## Detailed Instructions

### Create AWS bucket
1. Login to AWS console https://aws.amazon.com/console/
2. Navigate to _Services -> S3_.
3. Click _Create bucket_.
4. Fill out the bucket name and region
5. Ensure _Block all public access_ is ticked
6. Enable _Server-side encryption_ with _Amazon S3 key (SSE-S3)_
7. Click _Create bucket_.
8. Set the following as _Cross-origin resource sharing (CORS)_:
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
9. Navigate to _My Security Credentials_.
10. In the _Access keys_ section click on the _Create New Access Key_ button.
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
5. Run the following scheduled tasks:
```
php admin/cli/scheduled_task.php --execute='\tool_objectfs\task\check_objects_location'
php admin/cli/scheduled_task.php --execute='\tool_objectfs\task\push_objects_to_storage'
php admin/cli/scheduled_task.php --execute='\tool_objectfs\task\delete_local_objects'
php admin/cli/scheduled_task.php --execute='\tool_objectfs\task\generate_status_report'
```
6. Access the _/admin/tool/objectfs/object_status.php_ page.
7. Confirm, that all files have been moved to the external storage: _Marked as only in filedir_ and _Duplicated in filedir and external storage_ should be 0.

### Generate CloudFront keys
ObjectFS can use a CloudFront key from either the local filesystem or an admin setting. If using an admin setting, the key may be generated outside of the Moodle environment.

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
5. Navigate to https://console.aws.amazon.com/cloudfront/v3/home#/publickey.
6. Click _Create public key_.
7. Enter key name.
8. Enter key value. Use the following command to get the public key:
```
cat public_key.pem
```
9. Click _Create public key_.
10. Write down key ID from the https://console.aws.amazon.com/cloudfront/v3/home#/publickey page.
11. Navigate to https://console.aws.amazon.com/cloudfront/v3/home#/keygrouplist
12. Create a key group and select the public key created previously
13. Store the public and private key files somewhere secure

### Create CloudFront response headers policy

1. Navigate to https://console.aws.amazon.com/cloudfront/v3/home#/policies/responseHeaders
2. Click on _Create response headers policy_
3. _Name_: CORS-with-preflight-and-SecurityHeadersPolicy-ReadOnly
4. _Configure CORS_: disabled
5. _Strict-Transport-Security_: Enabled, origin override enabled
6. _X-Content-Type-Options_: Enabled, origin override enabled
7. _X-Frame-Options_: Disabled
8. _X-XSS-Protection_: Enabled, block, origin override enabled
9. _Referrer-Policy_: Enabled, strict-origin-when-cross-origin, origin override enabled
10. _Content-Security-Policy_: disabled

### Create CloudFront Origin request policy
1. Navigate to https://console.aws.amazon.com/cloudfront/v3/home#/policies/origin
2. Click on _Create origin request policy_
3. _Name_: IncludeResponseContentDisposition
4. _Headers_: Include the following headers
   - Access-Control-Request-Method
   - Access-Control-Request-Headers
5. _Query strings_: Include Specified query strings
   - response-content-disposition
   - response-content-type
6. _Cookies_: None

### Create CloudFront distribution
1. Navigate to https://console.aws.amazon.com/cloudfront/.
2. Click on _Create a CloudFront distribution_.
3. Choose your Amazon S3 bucket from _Origin domain_ dropdown menu.
4. _S3 bucket access_: Choose _Yes use OAI (bucket can restrict access to only CloudFront)_ and click _Create new OAI_.
5. _S3 bucket access -> Bucket policy_: Choose _Yes, update the bucket policy_.
6. _Viewer protocol policy_: Choose _Redirect HTTP to HTTPS_.
7. _Allowed HTTP methods_: Choose _GET, HEAD, OPTIONS_ and tick _OPTIONS_ under _Cache HTTP methods_.
8. _Restrict viewer access_: Choose _Yes -> Trusted key groups (recommended)_.
9. Add key group created earlier
10. _Cache key and origin requests_: Choose _Cache policy and origin request policy (recommended)_.
11. _Cache policy_: Choose CachingOptimized
12. _Origin request policy_: Choose IncludeResponseContentDisposition
13. _Response headers policy_: Choose CORS-with-preflight-and-SecurityHeadersPolicy-ReadOnly
14. Click _Create distribution_.
15. Navigate to https://console.aws.amazon.com/cloudfront/v3/home#/distributions.
16. Confirm, that _Status_ is _Enabled_ and _Last modified_ is changed from _Deploying_ to the date the distribution was created.
17. Open your distribution.
18. Write down _Distribution domain name_ (with https://).
> Note: If you have already setup Moodle behind a CloudFront distribution, it is also possible to use that same CloudFront distribution to serve files from objectfs. In this scenario, a specific prefix in the URL path directs traffic to the S3 Bucket (moodle.domain/objectfs/ for example). To achieve that, use the key_prefix option to add a prefix on your Bucket, and configure a second Origin on your existing CloudFront distribution that points to your Bucket. Setup a Behavior that uses that new Origin with the same prefix as the one you used as key_prefix in your Bucket. Follow all other instructions.

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
