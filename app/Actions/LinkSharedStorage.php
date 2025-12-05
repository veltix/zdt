<?php

declare(strict_types=1);

namespace App\Actions;

use App\Services\FileSync;
use App\Services\RemoteExecutor;
use App\ValueObjects\DeploymentConfig;
use App\ValueObjects\Release;
use Psr\Log\LoggerInterface;

final readonly class LinkSharedStorage
{
    public function __construct(
        private FileSync $fileSync,
        private RemoteExecutor $executor,
        private LoggerInterface $logger,
    ) {}

    public function handle(DeploymentConfig $config, Release $release): void
    {
        $sharedStoragePath = $config->getDeployPath().'/shared/storage';
        $releaseStoragePath = $release->path.'/storage';

        $this->logger->info('Linking shared storage');

        $this->executor->execute("rm -rf {$releaseStoragePath}", throwOnError: false);

        $this->fileSync->createSymlink($sharedStoragePath, $releaseStoragePath);

        $this->logger->info('Shared storage linked');
    }
}
