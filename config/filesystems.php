<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    |---------------------------------------------------------------------------
    |
    | OBJECTS ARE PRIVATE ON EVERY DISK EXCEPT `public`.
    |
    | Laravel's default visibility is already private, but two of the disks below
    | previously overrode it, and an object store is unforgiving about this: a
    | bucket serving publicly readable objects has no second gate behind it, and
    | the mistake is not visible from inside the application. The application
    | hands out short-lived signed URLs instead — see App\Utils\FileUtil.
    |
    | The `public` disk keeps public visibility because that is the entire point
    | of it. It is the LOCAL disk exposed through the storage symlink, and it is
    | for assets that are genuinely public. Never point it at an object store and
    | never put user uploads on it.
    |
    | CREDENTIALS ARE OPTIONAL EVERYWHERE, ON PURPOSE. Leaving the key/secret or
    | key-file variables unset makes each SDK fall back to its ambient credential
    | chain, which is how workload identity is meant to be consumed — an
    | attached role or service identity, no long-lived secret to distribute or
    | rotate. Static credentials remain supported for local development and for
    | S3-compatible storage that has no identity story, but they are not the
    | recommended production path.
    |
    */

    'disks' => [

        /*
         * The default. Private, on the container's own filesystem.
         *
         * `serve => true` lets temporaryUrl() work on a local disk by issuing a
         * Laravel signed route, so the same FileUtil call produces an expiring
         * URL in development and a provider-signed one in production. Without it
         * temporaryUrl() throws on local and the code paths diverge between
         * environments — which means the production path is never exercised.
         *
         * KNOWN SURFACE, accepted deliberately: this registers two routes,
         * `GET /storage/{path}` and `PUT /storage/{path}`. Both require a
         * signature derived from APP_KEY, so neither can be called without it,
         * and this application never mints an upload signature at all — the PUT
         * route is inert. Set FILESYSTEM_LOCAL_SERVE=false to remove both if you
         * do not want the routes to exist; temporaryUrl() on `local` then throws.
         *
         * A container filesystem is ephemeral and per-replica. This disk is the
         * right default for local development and for scratch files; anything
         * that must outlive a container or be visible to another replica belongs
         * on an object-store disk.
         */
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'serve' => filter_var(env('FILESYSTEM_LOCAL_SERVE', true), FILTER_VALIDATE_BOOL),
            'visibility' => 'private',
            'throw' => filter_var(env('FILESYSTEM_THROW', true), FILTER_VALIDATE_BOOL),
        ],

        /*
         * Deliberately public — see the note above. Local disk only.
         */
        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => filter_var(env('FILESYSTEM_THROW', true), FILTER_VALIDATE_BOOL),
        ],

        /*
         * S3 and any S3-compatible object store. With AWS_ACCESS_KEY_ID and
         * AWS_SECRET_ACCESS_KEY unset, Laravel omits the `credentials` key and
         * the AWS SDK uses its default provider chain — an instance/task role, a
         * web-identity token, whatever the platform attaches.
         *
         * AWS_ENDPOINT + AWS_USE_PATH_STYLE_ENDPOINT point the same driver at a
         * non-AWS S3-compatible endpoint, which is what keeps this disk from
         * being a single-provider commitment.
         */
        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => filter_var(env('AWS_USE_PATH_STYLE_ENDPOINT', false), FILTER_VALIDATE_BOOL),
            'visibility' => 'private',
            'throw' => filter_var(env('FILESYSTEM_THROW', true), FILTER_VALIDATE_BOOL),
        ],

        /*
         * Google Cloud Storage. With GOOGLE_CLOUD_KEY_FILE unset the client uses
         * Application Default Credentials, which resolves to the attached service
         * identity — no service-account JSON file to ship or rotate.
         *
         * `visibility_handler` must be set when the bucket has uniform
         * bucket-level access enabled (the current GCS default), because per-object
         * ACL calls fail on such a bucket. It is a class-string rather than an
         * object so the value survives `config:cache`, which serializes with
         * var_export().
         */
        'gcs' => [
            'driver' => 'gcs',
            'key_file_path' => env('GOOGLE_CLOUD_KEY_FILE'), // optional: /path/to/service-account.json
            'key_file' => [], // optional: Array of data that substitutes the .json file
            'project_id' => env('GOOGLE_CLOUD_PROJECT_ID'), // optional: is included in key file
            'bucket' => env('GOOGLE_CLOUD_STORAGE_BUCKET'),
            'path_prefix' => env('GOOGLE_CLOUD_STORAGE_PATH_PREFIX', ''), // optional
            'storage_api_uri' => env('GOOGLE_CLOUD_STORAGE_API_URI'),
            'api_endpoint' => env('GOOGLE_CLOUD_STORAGE_API_ENDPOINT'),
            'visibility' => 'private',
            'visibility_handler' => filter_var(env('GOOGLE_CLOUD_STORAGE_UNIFORM_BUCKET_LEVEL_ACCESS', true), FILTER_VALIDATE_BOOL)
                ? \League\Flysystem\GoogleCloudStorage\UniformBucketLevelAccessVisibility::class
                : null,
            'throw' => filter_var(env('FILESYSTEM_THROW', true), FILTER_VALIDATE_BOOL),
        ],

        /*
         * Azure Blob Storage is supported by the same Storage abstraction, but it
         * needs a Flysystem adapter this template does not ship. To use it:
         *
         *   composer require league/flysystem-azure-blob-storage
         *
         * then register the driver (Storage::extend) and add a disk here. No
         * application code changes — FileUtil and every caller keep working,
         * because they only ever talk to Storage. Left out rather than stubbed:
         * a disk entry for a driver that is not installed fails at resolve time
         * with a confusing "Driver [azure] is not supported".
         */

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
