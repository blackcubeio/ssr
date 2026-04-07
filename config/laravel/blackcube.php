<?php

declare(strict_types=1);

/**
 * Blackcube configuration for Laravel.
 * Publish with: php artisan vendor:publish --tag=blackcube-config
 */

return [
    'db' => [
        'dsn' => sprintf('%s:host=%s;dbname=%s;port=%d',
            env('DB_DRIVER', 'mysql'),
            env('DB_HOST', 'localhost'),
            env('DB_DATABASE', ''),
            (int) env('DB_PORT', 3306),
        ),
        'user' => env('DB_USER'),
        'password' => env('DB_PASSWORD'),
    ],
    'ssr' => [
        'scanAttributes' => true,
        'scanAliases' => [],
        'configHandlers' => [],
        'configErrorHandlers' => [],
        'excludedPrefixes' => ['api/'],
        'xeo' => true,
        'mdAlternate' => true,
    ],
    'fileProvider' => [
        'filesystems' => [
            '@blfs' => ['type' => 'local', 'path' => base_path('data')],
            '@bltmp' => ['type' => 'local', 'path' => storage_path('tmp')],
        ],
        'defaultAlias' => '@blfs',
    ],
    'cacheFile' => [
        'cachePath' => public_path('assets'),
        'cacheUrl' => '/assets',
    ],
];
