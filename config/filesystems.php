<?php

return [

    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [

        'programmes' => ['driver' => 'local', 'root' => storage_path('app/medias/programmes'), 'throw' => false],
        'upload' => ['driver' => 'local', 'root' => storage_path('app/medias/upload'), 'throw' => false],
        'news' => ['driver' => 'local', 'root' => storage_path('app/medias/news'), 'throw' => false],
        'clients' => ['driver' => 'local', 'root' => storage_path('app/medias/clients'), 'throw' => false],
        'csv' => ['driver' => 'local', 'root' => storage_path('app/medias/csv'), 'throw' => false],
        'export' => ['driver' => 'local', 'root' => storage_path('app/medias/export'), 'throw' => false],

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
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

    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
