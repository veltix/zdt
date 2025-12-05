<?php

declare(strict_types=1);

namespace App\Actions;

use App\Services\RemoteExecutor;
use App\ValueObjects\DeploymentConfig;
use App\ValueObjects\Release;
use Psr\Log\LoggerInterface;

final readonly class WarmCaches
{
    public function __construct(
        private RemoteExecutor $executor,
        private LoggerInterface $logger,
    ) {}

    public function handle(DeploymentConfig $config, Release $release): void
    {
        $this->logger->info('Warming caches...');

        foreach ($config->getHooks('after_activate') as $hook) {
            $this->executor->executeInDirectory($release->path, $hook);
        }

        sleep(2);

        $this->logger->info('Caches warmed');
    }
}
