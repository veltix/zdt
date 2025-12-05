<?php

declare(strict_types=1);

return [
    'server' => [
        'host' => 'test.example.com',
        'port' => 22,
        'username' => 'testuser',
        'key_path' => __DIR__.'/test-key',
        'timeout' => 300,
    ],

    'repository' => [
        'url' => 'git@github.com:test/repo.git',
        'branch' => 'main',
    ],

    'paths' => [
        'deploy_to' => '/var/www/test-app',
    ],

    'options' => [
        'keep_releases' => 3,
        'use_composer' => true,
        'use_npm' => false,
        'run_migrations' => true,
    ],

    'hooks' => [
        'before_clone' => [],
        'after_clone' => [
            'composer install',
        ],
        'before_activate' => [
            'php artisan migrate --force',
        ],
        'after_activate' => [
            'php artisan queue:restart',
        ],
        'after_rollback' => [],
    ],

    'health_check' => [
        'enabled' => true,
        'url' => 'https://test.example.com/health',
        'timeout' => 30,
    ],
];
