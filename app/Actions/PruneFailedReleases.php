<?php

declare(strict_types=1);

namespace App\Actions;

use App\Services\RemoteExecutor;
use App\ValueObjects\DeploymentConfig;
use Psr\Log\LoggerInterface;

final readonly class PruneFailedReleases
{
    public function __construct(
        private RemoteExecutor $executor,
        private LoggerInterface $logger,
    ) {}

    public function handle(DeploymentConfig $config): void
    {
        $releasesPath = $config->getDeployPath().'/releases';
        $currentSymlink = $config->getDeployPath().'/current';

        $this->logger->info('Pruning failed releases...');

        $currentResult = $this->executor->execute(
            "readlink {$currentSymlink}",
            throwOnError: false
        );

        $currentRelease = $currentResult->isSuccessful() ? basename(mb_trim($currentResult->output)) : null;

        $listResult = $this->executor->execute("ls {$releasesPath}", throwOnError: false);

        if ($listResult->isFailed()) {
            return;
        }

        $allReleases = array_filter(explode("\n", mb_trim($listResult->output)));

        foreach ($allReleases as $release) {
            $release = mb_trim($release);

            if ($release === $currentRelease) {
                continue;
            }

            $releasePath = $releasesPath.'/'.$release;

            if ($config->shouldUseComposer()) {
                $checkVendor = $this->executor->execute(
                    "test -d {$releasePath}/vendor",
                    throwOnError: false
                );

                if ($checkVendor->isFailed()) {
                    $this->logger->info("Removing incomplete release: {$release}");
                    $this->executor->execute("rm -rf {$releasePath}", throwOnError: false);
                }
            }
        }

        $this->logger->info('Failed releases pruned');
    }
}
