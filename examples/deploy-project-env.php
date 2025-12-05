<?php

declare(strict_types=1);

/**
 * ZDT Deployment Configuration
 *
 * This file should be committed to your repository and provides
 * deployment configuration that can be versioned with your code.
 *
 * Environment variables will override values from this file.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | SSH Connection
    |--------------------------------------------------------------------------
    |
    | Configure SSH connection details for your deployment server.
    |
    */
    'host' => env('DEPLOY_HOST', 'your-server.com'),
    'username' => env('DEPLOY_USERNAME', 'deploy'),
    'key_path' => env('DEPLOY_KEY_PATH', '~/.ssh/id_rsa'),
    'port' => (int) env('DEPLOY_PORT', 22),
    'timeout' => (int) env('DEPLOY_TIMEOUT', 300),

    /*
    |--------------------------------------------------------------------------
    | Repository
    |--------------------------------------------------------------------------
    |
    | Git repository URL and branch to deploy.
    |
    */
    'repo_url' => env('DEPLOY_REPO_URL', 'git@github.com:your-username/your-repo.git'),
    'branch' => env('DEPLOY_BRANCH', 'main'),

    /*
    |--------------------------------------------------------------------------
    | Deployment Path
    |--------------------------------------------------------------------------
    |
    | The absolute path on your server where the application will be deployed.
    |
    */
    'path' => env('DEPLOY_PATH', '/var/www/your-app'),

    /*
    |--------------------------------------------------------------------------
    | Release Management
    |--------------------------------------------------------------------------
    |
    | Number of releases to keep on the server. Older releases will be
    | automatically removed after successful deployments.
    |
    */
    'keep_releases' => (int) env('DEPLOY_KEEP_RELEASES', 5),

    /*
    |--------------------------------------------------------------------------
    | Custom Shared Paths
    |--------------------------------------------------------------------------
    |
    | Additional files or directories to share across releases.
    | Format: 'path/in/release' => 'path/in/shared'
    |
    | Default shared: .env (copied), storage/ (symlinked)
    |
    */
    'shared_paths' => [
        // 'resources/lang' => 'lang',
        // 'public/uploads' => 'uploads',
    ],

    /*
    |--------------------------------------------------------------------------
    | Deployment Hooks
    |--------------------------------------------------------------------------
    |
    | Commands to execute at different stages of deployment.
    | Each command is executed in the release directory.
    |
    */
    'hooks' => [
        'before_clone' => env('DEPLOY_HOOKS_BEFORE_CLONE', ''),

        'after_clone' => env('DEPLOY_HOOKS_AFTER_CLONE', implode("\n", [
            'composer install --no-dev --optimize-autoloader',
        ])),

        'before_activate' => env('DEPLOY_HOOKS_BEFORE_ACTIVATE', implode("\n", [
            'php artisan migrate --force',
            'php artisan config:cache',
            'php artisan route:cache',
            'php artisan view:cache',
        ])),

        'after_activate' => env('DEPLOY_HOOKS_AFTER_ACTIVATE', implode("\n", [
            'php artisan queue:restart',
        ])),

        'after_rollback' => env('DEPLOY_HOOKS_AFTER_ROLLBACK', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Checks
    |--------------------------------------------------------------------------
    |
    | Validate deployments with HTTP health checks. If the check fails,
    | ZDT will automatically rollback to the previous release.
    |
    */
    'health_check' => [
        'enabled' => env('DEPLOY_HEALTH_CHECK_ENABLED', false),
        'url' => env('DEPLOY_HEALTH_CHECK_URL', null),
        'timeout' => (int) env('DEPLOY_HEALTH_CHECK_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Backups
    |--------------------------------------------------------------------------
    |
    | Automatically backup your database before running migrations.
    | Backups are compressed and stored in the backups/ directory.
    |
    */
    'database_backup' => [
        'enabled' => env('DEPLOY_DB_BACKUP_ENABLED', false),
        'connection' => env('DEPLOY_DB_CONNECTION', 'mysql'),
        'host' => env('DEPLOY_DB_HOST', 'localhost'),
        'port' => (int) env('DEPLOY_DB_PORT', 3306),
        'database' => env('DEPLOY_DB_DATABASE', null),
        'username' => env('DEPLOY_DB_USERNAME', null),
        'password' => env('DEPLOY_DB_PASSWORD', null),
        'keep_backups' => (int) env('DEPLOY_DB_KEEP_BACKUPS', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Send deployment notifications via webhooks (Slack, Discord, etc.).
    |
    */
    'notification_webhook' => env('DEPLOY_NOTIFICATION_WEBHOOK', null),
];
