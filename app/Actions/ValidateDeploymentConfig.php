<?php

declare(strict_types=1);

namespace App\Actions;

use App\ValueObjects\DeploymentConfig;

final readonly class ValidateDeploymentConfig
{
    public function handle(string $configPath): DeploymentConfig
    {
        $this->loadEnvDeploymentFile();

        if ($this->hasEnvironmentConfig()) {
            $config = $this->getConfigFromEnvironment();
        } elseif (file_exists($configPath)) {
            $config = require $configPath;
        } else {
            $config = config('deploy');
        }

        return DeploymentConfig::fromArray($config);
    }

    private function hasEnvironmentConfig(): bool
    {
        return getenv('DEPLOY_HOST') !== false
            && getenv('DEPLOY_USERNAME') !== false
            && getenv('DEPLOY_REPO_URL') !== false;
    }

    private function getConfigFromEnvironment(): array
    {
        return [
            'server' => [
                'host' => getenv('DEPLOY_HOST'),
                'port' => (int) (getenv('DEPLOY_PORT') ?: 22),
                'username' => getenv('DEPLOY_USERNAME'),
                'key_path' => getenv('DEPLOY_KEY_PATH') ?: '~/.ssh/id_rsa',
                'timeout' => (int) (getenv('DEPLOY_TIMEOUT') ?: 300),
            ],
            'repository' => [
                'url' => getenv('DEPLOY_REPO_URL'),
                'branch' => getenv('DEPLOY_BRANCH') ?: 'main',
            ],
            'paths' => [
                'deploy_to' => getenv('DEPLOY_PATH') ?: '/var/www/app',
            ],
            'shared_paths' => [],
            'options' => [
                'keep_releases' => (int) (getenv('DEPLOY_KEEP_RELEASES') ?: 5),
                'use_composer' => filter_var(getenv('DEPLOY_USE_COMPOSER') ?: 'true', FILTER_VALIDATE_BOOLEAN),
                'use_npm' => filter_var(getenv('DEPLOY_USE_NPM'), FILTER_VALIDATE_BOOLEAN),
                'run_migrations' => filter_var(getenv('DEPLOY_RUN_MIGRATIONS') ?: 'true', FILTER_VALIDATE_BOOLEAN),
            ],
            'hooks' => [
                'before_clone' => array_filter(explode("\n", getenv('DEPLOY_HOOKS_BEFORE_CLONE') ?: '')),
                'after_clone' => array_filter(explode("\n", getenv('DEPLOY_HOOKS_AFTER_CLONE') ?: 'composer install --no-dev --optimize-autoloader')),
                'before_activate' => array_filter(explode("\n", getenv('DEPLOY_HOOKS_BEFORE_ACTIVATE') ?: '')),
                'after_activate' => array_filter(explode("\n", getenv('DEPLOY_HOOKS_AFTER_ACTIVATE') ?: '')),
                'after_rollback' => array_filter(explode("\n", getenv('DEPLOY_HOOKS_AFTER_ROLLBACK') ?: '')),
            ],
            'health_check' => [
                'enabled' => filter_var(getenv('DEPLOY_HEALTH_CHECK_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN),
                'url' => getenv('DEPLOY_HEALTH_CHECK_URL') ?: null,
                'timeout' => (int) (getenv('DEPLOY_HEALTH_CHECK_TIMEOUT') ?: 30),
            ],
            'database' => [
                'backup_enabled' => filter_var(getenv('DEPLOY_DB_BACKUP_ENABLED'), FILTER_VALIDATE_BOOLEAN),
                'connection' => getenv('DEPLOY_DB_CONNECTION') ?: 'mysql',
                'host' => getenv('DEPLOY_DB_HOST') ?: 'localhost',
                'port' => (int) (getenv('DEPLOY_DB_PORT') ?: 0),
                'database' => getenv('DEPLOY_DB_DATABASE') ?: null,
                'username' => getenv('DEPLOY_DB_USERNAME') ?: null,
                'password' => getenv('DEPLOY_DB_PASSWORD') ?: null,
                'keep_backups' => (int) (getenv('DEPLOY_DB_KEEP_BACKUPS') ?: 5),
            ],
            'notifications' => [
                'webhook_url' => getenv('DEPLOY_NOTIFICATION_WEBHOOK') ?: null,
            ],
        ];
    }

    /**
     * Load .env.deployment file if it exists.
     * Checks multiple locations in priority order.
     */
    private function loadEnvDeploymentFile(): void
    {
        $deployPath = getenv('DEPLOY_PATH');
        $envPaths = [];

        if ($deployPath) {
            $envPaths[] = mb_rtrim($deployPath, '/').'/shared/.env.deployment';
        }

        $envPaths[] = '/var/www/shared/.env.deployment';
        $envPaths[] = getcwd().'/../../shared/.env.deployment';

        $envPaths[] = getcwd().'/.env.deployment';

        foreach ($envPaths as $envPath) {
            if (! file_exists($envPath)) {
                continue;
            }

            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = mb_trim($line);

                if (empty($line) || str_starts_with($line, '#')) {
                    continue;
                }

                if (! str_contains($line, '=')) {
                    continue;
                }

                [$key, $value] = explode('=', $line, 2);
                $key = mb_trim($key);
                $value = mb_trim($value);

                if (getenv($key) === false) {
                    putenv("$key=$value");
                }
            }

            break;
        }
    }
}
