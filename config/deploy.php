<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Server Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the SSH connection details for your deployment server.
    | Only SSH key authentication is supported for security.
    |
    */
    'server' => [
        'host' => env('DEPLOY_HOST'),
        'port' => env('DEPLOY_PORT', 22),
        'username' => env('DEPLOY_USERNAME'),
        'key_path' => env('DEPLOY_KEY_PATH', '~/.ssh/id_rsa'),
        'timeout' => env('DEPLOY_TIMEOUT', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Repository Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the Git repository details for deployment.
    |
    */
    'repository' => [
        'url' => env('DEPLOY_REPO_URL'),
        'branch' => env('DEPLOY_BRANCH', 'main'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Deployment Paths
    |--------------------------------------------------------------------------
    |
    | Configure the deployment paths on the remote server.
    |
    */
    'paths' => [
        'deploy_to' => env('DEPLOY_PATH', '/var/www/app'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Shared Paths
    |--------------------------------------------------------------------------
    |
    | Paths that should be shared between releases (symlinked from shared/).
    | The .env file is always copied and storage/ is always symlinked.
    |
    | Define additional shared paths as:
    | - Directories: 'resources/lang' => 'lang'
    | - Files: 'config/custom.php' => 'config/custom.php'
    |
    | Format: 'path/in/release' => 'path/in/shared'
    |
    | Example: To share translations:
    | 'resources/lang' => 'lang'
    | This creates: shared/lang -> releases/{timestamp}/resources/lang
    |
    */
    'shared_paths' => [
        // 'resources/lang' => 'lang',
        // 'public/uploads' => 'uploads',
    ],

    /*
    |--------------------------------------------------------------------------
    | Deployment Options
    |--------------------------------------------------------------------------
    |
    | Configure deployment behavior and features.
    |
    */
    'options' => [
        'keep_releases' => env('DEPLOY_KEEP_RELEASES', 5),
        'use_composer' => true,
        'use_npm' => false,
        'run_migrations' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Deployment Hooks
    |--------------------------------------------------------------------------
    |
    | Define custom scripts to run at various stages of deployment.
    | Hook failures will abort the deployment.
    |
    | You can define hooks via environment variables using multiline strings:
    | DEPLOY_HOOKS_BEFORE_CLONE="command1\ncommand2"
    | DEPLOY_HOOKS_AFTER_CLONE="composer install --no-dev"
    | DEPLOY_HOOKS_BEFORE_ACTIVATE="php artisan migrate --force\nphp artisan cache:clear"
    | DEPLOY_HOOKS_AFTER_ACTIVATE="php artisan queue:restart"
    | DEPLOY_HOOKS_AFTER_ROLLBACK="php artisan cache:clear"
    |
    */
    'hooks' => [
        'before_clone' => array_filter(
            env('DEPLOY_HOOKS_BEFORE_CLONE')
                ? explode("\n", mb_trim(env('DEPLOY_HOOKS_BEFORE_CLONE')))
                : []
        ),

        'after_clone' => array_filter(
            env('DEPLOY_HOOKS_AFTER_CLONE')
                ? explode("\n", mb_trim(env('DEPLOY_HOOKS_AFTER_CLONE')))
                : ['composer install --no-dev --optimize-autoloader']
        ),

        'before_activate' => array_filter(
            env('DEPLOY_HOOKS_BEFORE_ACTIVATE')
                ? explode("\n", mb_trim(env('DEPLOY_HOOKS_BEFORE_ACTIVATE')))
                : [
                    'php artisan migrate --force',
                    'php artisan config:cache',
                    'php artisan route:cache',
                    'php artisan view:cache',
                ]
        ),

        'after_activate' => array_filter(
            env('DEPLOY_HOOKS_AFTER_ACTIVATE')
                ? explode("\n", mb_trim(env('DEPLOY_HOOKS_AFTER_ACTIVATE')))
                : ['php artisan queue:restart']
        ),

        'after_rollback' => array_filter(
            env('DEPLOY_HOOKS_AFTER_ROLLBACK')
                ? explode("\n", mb_trim(env('DEPLOY_HOOKS_AFTER_ROLLBACK')))
                : []
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Check Configuration
    |--------------------------------------------------------------------------
    |
    | Configure health checks to validate deployments.
    |
    */
    'health_check' => [
        'enabled' => true,
        'url' => null, // Optional HTTP health check URL
        'timeout' => 30,
    ],
];
