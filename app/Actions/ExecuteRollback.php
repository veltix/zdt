<?php

declare(strict_types=1);

namespace App\Actions;

use App\Services\FileSync;
use App\Services\RemoteExecutor;
use App\ValueObjects\DeploymentConfig;
use App\ValueObjects\Release;
use Psr\Log\LoggerInterface;

final readonly class ExecuteRollback
{
    public function __construct(
        private FileSync $fileSync,
        private RemoteExecutor $executor,
        private LoggerInterface $logger,
    ) {}

    public function handle(DeploymentConfig $config, Release $target): void
    {
        $currentSymlink = $config->getDeployPath().'/current';

        $this->logger->info("Rolling back to release: {$target->name}");

        $this->fileSync->createSymlink($target->path, $currentSymlink);

        foreach ($config->getHooks('after_rollback') as $hook) {
            $this->executor->executeInDirectory($target->path, $hook);
        }

        $this->logger->info('Rollback completed successfully');
    }
}
