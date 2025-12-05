<?php

declare(strict_types=1);

namespace App\Actions;

use App\Services\FileSync;
use App\Services\RemoteExecutor;
use App\ValueObjects\DeploymentConfig;
use App\ValueObjects\Release;
use Psr\Log\LoggerInterface;

final readonly class LinkCustomSharedPaths
{
    public function __construct(
        private FileSync $fileSync,
        private RemoteExecutor $executor,
        private LoggerInterface $logger,
    ) {}

    public function handle(DeploymentConfig $config, Release $release): void
    {
        $sharedPaths = $config->getSharedPaths();

        if (empty($sharedPaths)) {
            return;
        }

        $this->logger->info('Linking custom shared paths');

        foreach ($sharedPaths as $releasePath => $sharedPath) {
            $this->linkPath($config, $release, $releasePath, $sharedPath);
        }

        $this->logger->info('Custom shared paths linked');
    }

    private function linkPath(
        DeploymentConfig $config,
        Release $release,
        string $releasePath,
        string $sharedPath
    ): void {
        $sharedFullPath = $config->getDeployPath().'/shared/'.$sharedPath;
        $releaseFullPath = $release->path.'/'.$releasePath;

        $parentDir = dirname($sharedFullPath);
        $this->executor->execute("mkdir -p {$parentDir}");

        if (! $this->executor->execute("test -e {$sharedFullPath}", throwOnError: false)->isSuccessful()) {
            if (str_ends_with($releasePath, '/') || ! str_contains(basename($releasePath), '.')) {
                $this->executor->execute("mkdir -p {$sharedFullPath}");
                $this->logger->info("Created shared directory: {$sharedPath}");
            } else {
                $this->executor->execute("mkdir -p {$sharedFullPath}");
                $this->logger->info("Created shared path: {$sharedPath}");
            }
        }

        $this->executor->execute("rm -rf {$releaseFullPath}", throwOnError: false);

        $releaseParentDir = dirname($releaseFullPath);
        $this->executor->execute("mkdir -p {$releaseParentDir}");

        $this->fileSync->createSymlink($sharedFullPath, $releaseFullPath);

        $this->logger->info("Linked {$releasePath} → shared/{$sharedPath}");
    }
}
