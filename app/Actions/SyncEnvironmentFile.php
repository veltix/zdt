<?php

declare(strict_types=1);

namespace App\Actions;

use App\Services\FileSync;
use App\Services\RemoteExecutor;
use App\ValueObjects\DeploymentConfig;
use App\ValueObjects\Release;
use Psr\Log\LoggerInterface;

final readonly class SyncEnvironmentFile
{
    public function __construct(
        private FileSync $fileSync,
        private RemoteExecutor $executor,
        private LoggerInterface $logger,
    ) {}

    public function handle(DeploymentConfig $config, Release $release): void
    {
        $sharedEnvPath = $config->getDeployPath().'/shared/.env';
        $releaseEnvPath = $release->path.'/.env';

        $this->logger->info('Syncing environment file');

        if (! $this->executor->execute("test -f {$sharedEnvPath}", throwOnError: false)->isSuccessful()) {
            $this->logger->warning(
                "Shared .env file not found at {$sharedEnvPath}. Please create it manually."
            );

            return;
        }

        $this->fileSync->copySharedFile($sharedEnvPath, $releaseEnvPath);

        $this->logger->info('Environment file synced');
    }
}
