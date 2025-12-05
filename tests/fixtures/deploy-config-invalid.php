<?php

declare(strict_types=1);

return [
    'server' => [
        'host' => 'localhost', // Satisfy AppServiceProvider
        'port' => 22,
        'username' => 'testuser',
        'key_path' => '/tmp/key',
        'timeout' => 300,
    ],

    'repository' => [
        // Missing required 'url'
        'branch' => 'main',
    ],

    'paths' => [
        // Missing required 'deploy_to'
    ],

    'options' => [
        'keep_releases' => 3,
    ],

    'hooks' => [],

    'health_check' => [
        'enabled' => false,
    ],
];
