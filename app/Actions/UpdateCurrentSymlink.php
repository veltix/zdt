<?php

declare(strict_types=1);

namespace App\Actions;

use App\Services\FileSync;
use App\ValueObjects\DeploymentConfig;
use App\ValueObjects\Release;
use Psr\Log\LoggerInterface;

final readonly class UpdateCurrentSymlink
{
    public function __construct(
        private FileSync $fileSync,
        private LoggerInterface $logger,
    ) {}

    public function handle(DeploymentConfig $config, Release $release): void
    {
        $currentSymlink = $config->getDeployPath().'/current';

        $this->logger->info("Activating release: {$release->name}");

        $this->fileSync->createSymlink($release->path, $currentSymlink);

        $this->logger->info('Release activated successfully - ZERO DOWNTIME ACHIEVED!');
    }
}
