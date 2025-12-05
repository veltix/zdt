<?php

declare(strict_types=1);

namespace App\Actions;

use App\Services\RemoteExecutor;
use App\ValueObjects\DeploymentConfig;
use App\ValueObjects\Release;
use Psr\Log\LoggerInterface;

final readonly class CheckoutBranch
{
    public function __construct(
        private RemoteExecutor $executor,
        private LoggerInterface $logger,
    ) {}

    public function handle(DeploymentConfig $config, Release $release): Release
    {
        $branch = $config->getBranch();

        $this->logger->info("Checking out branch: {$branch}");

        $this->executor->executeInDirectory($release->path, "git checkout {$branch}");
        $this->executor->executeInDirectory($release->path, 'git pull origin '.$branch);

        $result = $this->executor->executeInDirectory($release->path, 'git rev-parse HEAD');
        $commitHash = mb_trim($result->output);

        $this->logger->info("Checked out commit: {$commitHash}");

        return $release->withBranch($branch)->withCommitHash($commitHash);
    }
}
