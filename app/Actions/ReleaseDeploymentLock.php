<?php

declare(strict_types=1);

namespace App\Actions;

use App\Services\RemoteExecutor;
use App\ValueObjects\DeploymentConfig;
use Psr\Log\LoggerInterface;

final readonly class ReleaseDeploymentLock
{
    public function __construct(
        private RemoteExecutor $executor,
        private LoggerInterface $logger,
    ) {}

    public function handle(DeploymentConfig $config): void
    {
        $lockFile = $this->getLockPath($config);

        $result = $this->executor->execute("rm -f {$lockFile}", throwOnError: false);

        if ($result->isSuccessful()) {
            $this->logger->info("Deployment lock released: {$lockFile}");
        } else {
            $this->logger->warning("Failed to release deployment lock: {$lockFile}");
        }
    }

    private function getLockPath(DeploymentConfig $config): string
    {
        return $config->getDeployPath().'/.deploy.lock';
    }
}
