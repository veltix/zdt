<?php

declare(strict_types=1);

namespace App\Actions;

use App\Exceptions\RollbackException;
use App\Services\RemoteExecutor;
use App\ValueObjects\DeploymentConfig;
use App\ValueObjects\Release;
use Psr\Log\LoggerInterface;

final readonly class IdentifyRollbackTarget
{
    public function __construct(
        private RemoteExecutor $executor,
        private LoggerInterface $logger,
    ) {}

    public function handle(DeploymentConfig $config, ?string $targetReleaseName = null): Release
    {
        $releasesPath = $config->getDeployPath().'/releases';
        $currentSymlink = $config->getDeployPath().'/current';

        $currentResult = $this->executor->execute("readlink {$currentSymlink}");
        $currentReleasePath = mb_trim($currentResult->output);
        $currentReleaseName = basename($currentReleasePath);

        $this->logger->info("Current release: {$currentReleaseName}");

        if ($targetReleaseName !== null) {
            $targetPath = $releasesPath.'/'.$targetReleaseName;

            return new Release(
                name: $targetReleaseName,
                path: $targetPath,
                createdAt: now()->toDateTimeImmutable(),
            );
        }

        $listResult = $this->executor->execute("ls -t {$releasesPath}");
        $releases = array_filter(explode("\n", mb_trim($listResult->output)));

        $foundCurrent = false;
        foreach ($releases as $release) {
            $release = mb_trim($release);

            if ($release === $currentReleaseName) {
                $foundCurrent = true;

                continue;
            }

            if ($foundCurrent) {
                $this->logger->info("Rollback target identified: {$release}");

                return new Release(
                    name: $release,
                    path: $releasesPath.'/'.$release,
                    createdAt: now()->toDateTimeImmutable(),
                );
            }
        }

        throw new RollbackException('No previous release found to rollback to');
    }
}
