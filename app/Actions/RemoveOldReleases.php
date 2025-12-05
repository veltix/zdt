<?php

declare(strict_types=1);

namespace App\Actions;

use App\Services\RemoteExecutor;
use App\ValueObjects\DeploymentConfig;
use Psr\Log\LoggerInterface;

final readonly class RemoveOldReleases
{
    public function __construct(
        private RemoteExecutor $executor,
        private LoggerInterface $logger,
    ) {}

    public function handle(DeploymentConfig $config): void
    {
        $keepReleases = $config->getKeepReleases();
        $releasesPath = $config->getDeployPath().'/releases';

        $this->logger->info("Cleaning up old releases (keeping {$keepReleases})...");

        $command = "ls -t {$releasesPath} | tail -n +".($keepReleases + 1);
        $result = $this->executor->execute($command, throwOnError: false);

        if ($result->isFailed() || empty(mb_trim($result->output))) {
            $this->logger->info('No old releases to remove');

            return;
        }

        $oldReleases = array_filter(explode("\n", mb_trim($result->output)));

        foreach ($oldReleases as $release) {
            $releasePath = $releasesPath.'/'.mb_trim($release);
            $this->logger->info("Removing old release: {$release}");
            $this->executor->execute("rm -rf {$releasePath}");
        }

        $this->logger->info('Old releases cleaned up');
    }
}
