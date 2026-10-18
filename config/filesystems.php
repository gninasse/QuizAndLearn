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
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            // Désactivé : Laravel enregistrerait sa propre route /storage/{path}
            // (URLs signées du disque privé) qui masquerait MediaController.
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
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
            'report' => false,
        ],

        // Vidéos du pilote s3_hls (S3, MinIO, R2… via VIDEO_S3_ENDPOINT).
        'videos' => [
            'driver' => 's3',
            'key' => env('VIDEO_S3_KEY'),
            'secret' => env('VIDEO_S3_SECRET'),
            'region' => env('VIDEO_S3_REGION', 'us-east-1'),
            'bucket' => env('VIDEO_S3_BUCKET'),
            'endpoint' => env('VIDEO_S3_ENDPOINT'),
            'use_path_style_endpoint' => env('VIDEO_S3_PATH_STYLE', false),
            'visibility' => 'private',
            // Délais courts : un point d'accès injoignable ne doit pas bloquer le back-office.
            'http' => ['connect_timeout' => 5, 'timeout' => 15],
            'throw' => true,
            'report' => false,
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

    // Volontairement vide : les médias uploadés sont servis par la route
    // authentifiée /storage/{path} (MediaController). Un lien symbolique
    // public/storage court-circuiterait cette protection — ne pas lancer
    // `php artisan storage:link`.
    'links' => [],

];
