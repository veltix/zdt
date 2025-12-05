<?php

declare(strict_types=1);

namespace App\Actions;

use App\Services\RemoteExecutor;
use App\ValueObjects\DeploymentConfig;
use App\ValueObjects\Release;
use Psr\Log\LoggerInterface;

final readonly class RunDatabaseMigrations
{
    public function __construct(
        private RemoteExecutor $executor,
        private LoggerInterface $logger,
    ) {}

    public function handle(DeploymentConfig $config, Release $release): void
    {
        if (! $config->shouldRunMigrations()) {
            $this->logger->info('Skipping database migrations (disabled in config)');

            return;
        }

        $this->logger->info('Running database migrations...');

        $this->executor->executeInDirectory(
            $release->path,
            'php artisan migrate --force'
        );

        $this->logger->info('Database migrations completed');
    }
}
