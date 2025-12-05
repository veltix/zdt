<?php

declare(strict_types=1);

namespace App\Actions;

use App\Exceptions\HealthCheckFailedException;
use App\Services\RemoteExecutor;
use App\ValueObjects\DeploymentConfig;
use App\ValueObjects\HealthCheckResult;
use App\ValueObjects\Release;
use Psr\Log\LoggerInterface;

final readonly class ValidateNewRelease
{
    public function __construct(
        private RemoteExecutor $executor,
        private LoggerInterface $logger,
    ) {}

    public function handle(DeploymentConfig $config, Release $release): HealthCheckResult
    {
        $this->logger->info('Validating new release...');

        // Check if release directory is complete
        if (! $this->executor->execute("test -d {$release->path}", throwOnError: false)->isSuccessful()) {
            throw new HealthCheckFailedException('Release directory does not exist');
        }

        // Check if .env file exists
        if (! $this->executor->execute("test -f {$release->path}/.env", throwOnError: false)->isSuccessful()) {
            $this->logger->warning('.env file not found in release');
        }

        // Check if vendor directory exists (if using Composer)
        if ($config->shouldUseComposer()) {
            if (! $this->executor->execute("test -d {$release->path}/vendor", throwOnError: false)->isSuccessful()) {
                throw new HealthCheckFailedException('Composer dependencies not installed');
            }
        }

        // Optional: Run php artisan config:check if available
        $this->executor->executeInDirectory(
            $release->path,
            'php artisan config:check',
            throwOnError: false
        );

        $this->logger->info('Release validation passed');

        return new HealthCheckResult(healthy: true);
    }
}
