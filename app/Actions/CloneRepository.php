<?php

declare(strict_types=1);

namespace App\Actions;

use App\Services\RemoteExecutor;
use App\ValueObjects\DeploymentConfig;
use App\ValueObjects\Release;
use Psr\Log\LoggerInterface;

final readonly class CloneRepository
{
    public function __construct(
        private RemoteExecutor $executor,
        private LoggerInterface $logger,
    ) {}

    public function handle(DeploymentConfig $config, Release $release): void
    {
        $repositoryUrl = $config->getRepositoryUrl();

        $this->logger->info("Cloning repository: {$repositoryUrl}");

        if (preg_match('/(?:git@|https?:\/\/)([^:\/]+)/', $repositoryUrl, $matches)) {
            $host = $matches[1];
            $this->logger->info("Adding {$host} host key to known_hosts");
            $this->executor->execute(
                "mkdir -p ~/.ssh && ssh-keyscan {$host} >> ~/.ssh/known_hosts 2>/dev/null || true",
                throwOnError: false
            );
        }

        foreach ($config->getHooks('before_clone') as $hook) {
            $this->executor->executeInDirectory($release->path, $hook);
        }

        $this->executor->execute("git clone {$repositoryUrl} {$release->path}");

        $this->logger->info('Repository cloned successfully');

        foreach ($config->getHooks('after_clone') as $hook) {
            $this->executor->executeInDirectory($release->path, $hook);
        }
    }
}
