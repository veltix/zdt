<?php

declare(strict_types=1);

namespace App\Actions;

use App\Services\RemoteExecutor;
use App\ValueObjects\DeploymentConfig;
use App\ValueObjects\Release;
use Psr\Log\LoggerInterface;

final readonly class InstallComposerDependencies
{
    public function __construct(
        private RemoteExecutor $executor,
        private LoggerInterface $logger,
    ) {}

    public function handle(DeploymentConfig $config, Release $release): void
    {
        if (! $config->shouldUseComposer()) {
            $this->logger->info('Skipping Composer installation (disabled in config)');

            return;
        }

        $this->logger->info('Installing Composer dependencies...');

        $this->executor->executeInDirectory(
            $release->path,
            'composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader'
        );

        $this->logger->info('Composer dependencies installed');
    }
}
