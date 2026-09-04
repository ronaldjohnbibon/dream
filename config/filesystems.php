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

    'default' => env('FILESYSTEM_DISK', 'r2-private'),

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
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root'   => storage_path('app/private'),
            'serve'  => true,
            'throw'  => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root'   => storage_path('app/public'),
            'throw'  => false,
            'report' => false,
        ],

        'r2-public' => [
            'driver'                  => 's3',
            'key'                     => env('R2_ACCESS_KEY_ID'),
            'secret'                  => env('R2_SECRET_ACCESS_KEY'),
            'region'                  => env('R2_REGION', 'auto'),
            'bucket'                  => env('R2_PUBLIC_BUCKET'),
            'url'                     => env('R2_PUBLIC_URL'),
            'endpoint'                => env('R2_ENDPOINT'),
            'use_path_style_endpoint' => env('R2_USE_PATH_STYLE_ENDPOINT', false),
            'throw'                   => false,
            'report'                  => false,
        ],

        'r2-private' => [
            'driver'                  => 's3',
            'key'                     => env('R2_ACCESS_KEY_ID'),
            'secret'                  => env('R2_SECRET_ACCESS_KEY'),
            'region'                  => env('R2_REGION', 'auto'),
            'bucket'                  => env('R2_PRIVATE_BUCKET'),
            'endpoint'                => env('R2_ENDPOINT'),
            'use_path_style_endpoint' => env('R2_USE_PATH_STYLE_ENDPOINT', false),
            'throw'                   => false,
            'report'                  => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | R2 serves application uploads directly or through authorized responses,
    | so the application does not create public storage symlinks.
    |
    */

    'links' => [],

];
