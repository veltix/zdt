<?php

declare(strict_types=1);

namespace App\Actions;

use App\Services\RemoteExecutor;
use App\ValueObjects\DeploymentConfig;
use App\ValueObjects\Release;
use Psr\Log\LoggerInterface;

final readonly class RecordDeployment
{
    public function __construct(
        private RemoteExecutor $executor,
        private LoggerInterface $logger,
    ) {}

    public function handle(DeploymentConfig $config, Release $release): void
    {
        $zdtPath = $config->getDeployPath().'/.zdt';
        $logFile = $zdtPath.'/deployment.log';

        $entry = json_encode([
            'event' => 'deployment_success',
            'timestamp' => now()->toIso8601String(),
            'release' => $release->name,
            'commit_hash' => $release->commitHash,
            'branch' => $release->branch,
        ]);

        $escapedEntry = escapeshellarg($entry);

        $this->executor->execute("echo {$escapedEntry} >> {$logFile}", throwOnError: false);

        $this->logger->info('Deployment recorded');
    }
}
