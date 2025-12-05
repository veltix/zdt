<?php

declare(strict_types=1);

namespace App\Actions;

use App\Services\RemoteExecutor;
use App\ValueObjects\DeploymentConfig;
use App\ValueObjects\Release;
use Psr\Log\LoggerInterface;

final readonly class OptimizeApplication
{
    public function __construct(
        private RemoteExecutor $executor,
        private LoggerInterface $logger,
    ) {}

    public function handle(DeploymentConfig $config, Release $release): void
    {
        $this->logger->info('Optimizing application...');

        foreach ($config->getHooks('before_activate') as $hook) {
            $this->executor->executeInDirectory($release->path, $hook);
        }

        $this->logger->info('Application optimized');
    }
}
