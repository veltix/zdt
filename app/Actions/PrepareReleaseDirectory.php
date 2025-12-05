<?php

declare(strict_types=1);

namespace App\Actions;

use App\Services\RemoteExecutor;
use App\ValueObjects\DeploymentConfig;
use App\ValueObjects\Release;
use Psr\Log\LoggerInterface;

final readonly class PrepareReleaseDirectory
{
    public function __construct(
        private RemoteExecutor $executor,
        private LoggerInterface $logger,
    ) {}

    public function handle(DeploymentConfig $config): Release
    {
        $release = Release::create($config->getDeployPath());

        $this->logger->info("Creating release directory: {$release->path}");

        $this->executor->execute("mkdir -p {$release->path}");

        $sharedPath = $config->getDeployPath().'/shared';
        $this->executor->execute("mkdir -p {$sharedPath}/storage/app");
        $this->executor->execute("mkdir -p {$sharedPath}/storage/framework/cache");
        $this->executor->execute("mkdir -p {$sharedPath}/storage/framework/sessions");
        $this->executor->execute("mkdir -p {$sharedPath}/storage/framework/views");
        $this->executor->execute("mkdir -p {$sharedPath}/storage/logs");

        $zdtPath = $config->getDeployPath().'/.zdt';
        $this->executor->execute("mkdir -p {$zdtPath}");

        $this->logger->info("Release directory prepared: {$release->name}");

        return $release;
    }
}
