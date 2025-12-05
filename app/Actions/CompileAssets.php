<?php

declare(strict_types=1);

namespace App\Actions;

use App\Services\RemoteExecutor;
use App\ValueObjects\DeploymentConfig;
use App\ValueObjects\Release;
use Psr\Log\LoggerInterface;

final readonly class CompileAssets
{
    public function __construct(
        private RemoteExecutor $executor,
        private LoggerInterface $logger,
    ) {}

    public function handle(DeploymentConfig $config, Release $release): void
    {
        if (! $config->shouldUseNpm()) {
            $this->logger->info('Skipping asset compilation (disabled in config)');

            return;
        }

        $this->logger->info('Compiling assets...');

        $this->executor->executeInDirectory($release->path, 'npm ci');

        $this->executor->executeInDirectory($release->path, 'npm run build');

        $this->logger->info('Assets compiled successfully');
    }
}
