<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\Exceptions\ValidationException;

final readonly class DeploymentConfig
{
    /**
     * @param  array<string, mixed>  $server
     * @param  array<string, mixed>  $repository
     * @param  array<string, mixed>  $paths
     * @param  array<string, mixed>  $options
     * @param  array<string, array<int, string>>  $hooks
     * @param  array<string, mixed>  $healthCheck
     * @param  array<string, string>  $sharedPaths
     * @param  array<string, mixed>  $database
     * @param  array<string, mixed>  $notifications
     */
    public function __construct(
        public array $server,
        public array $repository,
        public array $paths,
        public array $options,
        public array $hooks,
        public array $healthCheck,
        public array $sharedPaths = [],
        public array $database = [],
        public array $notifications = [],
    ) {
        $this->validate();
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            server: $config['server'] ?? [],
            repository: $config['repository'] ?? [],
            paths: $config['paths'] ?? [],
            options: $config['options'] ?? [],
            hooks: $config['hooks'] ?? [],
            healthCheck: $config['health_check'] ?? [],
            sharedPaths: $config['shared_paths'] ?? [],
            database: $config['database'] ?? [],
            notifications: $config['notifications'] ?? [],
        );
    }

    public function getServerCredentials(): ServerCredentials
    {
        return new ServerCredentials(
            host: $this->server['host'] ?? '',
            port: $this->server['port'] ?? 22,
            username: $this->server['username'] ?? '',
            keyPath: $this->expandPath($this->server['key_path'] ?? ''),
            timeout: $this->server['timeout'] ?? 300,
        );
    }

    public function getRepositoryUrl(): string
    {
        return $this->repository['url'] ?? '';
    }

    public function getBranch(): string
    {
        return $this->repository['branch'] ?? 'main';
    }

    public function getDeployPath(): string
    {
        return $this->paths['deploy_to'] ?? '/var/www/app';
    }

    public function getKeepReleases(): int
    {
        return $this->options['keep_releases'] ?? 5;
    }

    public function shouldUseComposer(): bool
    {
        return $this->options['use_composer'] ?? true;
    }

    public function shouldUseNpm(): bool
    {
        return $this->options['use_npm'] ?? false;
    }

    public function shouldRunMigrations(): bool
    {
        return $this->options['run_migrations'] ?? false;
    }

    /**
     * @return array<int, string>
     */
    public function getHooks(string $stage): array
    {
        return $this->hooks[$stage] ?? [];
    }

    public function isHealthCheckEnabled(): bool
    {
        return $this->healthCheck['enabled'] ?? false;
    }

    public function getHealthCheckUrl(): ?string
    {
        return $this->healthCheck['url'] ?? null;
    }

    public function getHealthCheckTimeout(): int
    {
        return $this->healthCheck['timeout'] ?? 30;
    }

    /**
     * @return array<string, string>
     */
    public function getSharedPaths(): array
    {
        return $this->sharedPaths;
    }

    public function shouldBackupDatabase(): bool
    {
        return ($this->database['backup_enabled'] ?? false) === true;
    }

    public function getDatabaseConnection(): ?string
    {
        return $this->database['connection'] ?? null;
    }

    public function getDatabaseHost(): ?string
    {
        return $this->database['host'] ?? null;
    }

    public function getDatabasePort(): ?int
    {
        $port = $this->database['port'] ?? null;

        return $port ? (int) $port : null;
    }

    public function getDatabaseName(): ?string
    {
        return $this->database['database'] ?? null;
    }

    public function getDatabaseUsername(): ?string
    {
        return $this->database['username'] ?? null;
    }

    public function getDatabasePassword(): ?string
    {
        return $this->database['password'] ?? null;
    }

    public function getKeepBackups(): int
    {
        return (int) ($this->database['keep_backups'] ?? 5);
    }

    public function getNotificationWebhook(): ?string
    {
        return $this->notifications['webhook_url'] ?? null;
    }

    private function validate(): void
    {
        if (empty($this->server['host'])) {
            throw new ValidationException('Server host is required in deployment configuration');
        }

        if (empty($this->server['username'])) {
            throw new ValidationException('Server username is required in deployment configuration');
        }

        if (empty($this->repository['url'])) {
            throw new ValidationException('Repository URL is required in deployment configuration');
        }

        if (empty($this->paths['deploy_to'])) {
            throw new ValidationException('Deployment path is required in deployment configuration');
        }
    }

    private function expandPath(string $path): string
    {
        if (str_starts_with($path, '~/')) {
            return str_replace('~', $_SERVER['HOME'] ?? '', $path);
        }

        return $path;
    }
}
