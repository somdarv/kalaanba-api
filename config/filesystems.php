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
    | Supported Drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],

        /*
         * Cloudflare R2, over its S3-compatible API.
         *
         * Three settings are not negotiable and are the ones people get wrong:
         *
         *  - region is the literal string 'auto'. R2 has no regions; the SDK
         *    still demands one to build a signature.
         *  - use_path_style_endpoint is true. Virtual-host style would resolve
         *    {bucket}.{account}.r2.cloudflarestorage.com, which does not exist.
         *  - the credentials are the S3 key pair from R2 > Manage R2 API
         *    Tokens, NOT a Cloudflare account API token. The two look alike and
         *    only one authenticates here.
         *
         * `url` is deliberately absent. R2's S3 endpoint is private and is not
         * the address a browser fetches from, so letting Flysystem derive a URL
         * from it would produce links that resolve for nobody. The public base
         * URL lives in `player.media.r2.public_url` and the driver builds the
         * address itself.
         *
         * `throw` is true, unlike the s3 disk above: a failed upload must
         * surface as an error the caller can report, not as a silent false that
         * leaves the player looking at an unchanged card.
         */
        'r2' => [
            'driver' => 's3',
            'key' => env('R2_ACCESS_KEY_ID'),
            'secret' => env('R2_SECRET_ACCESS_KEY'),
            'region' => 'auto',
            'bucket' => env('R2_BUCKET', 'kalaanba-dev-storage'),
            'endpoint' => env('R2_ACCOUNT_ID')
                ? 'https://'.env('R2_ACCOUNT_ID').'.r2.cloudflarestorage.com'
                : null,
            'use_path_style_endpoint' => true,
            'throw' => true,
        ],

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
