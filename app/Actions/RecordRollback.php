<?php

declare(strict_types=1);

namespace App\Actions;

use App\Services\RemoteExecutor;
use App\ValueObjects\DeploymentConfig;
use App\ValueObjects\Release;
use Psr\Log\LoggerInterface;

final readonly class RecordRollback
{
    public function __construct(
        private RemoteExecutor $executor,
        private LoggerInterface $logger,
    ) {}

    public function handle(DeploymentConfig $config, Release $target, ?Release $previous = null): void
    {
        $zdtPath = $config->getDeployPath().'/.zdt';
        $logFile = $zdtPath.'/deployment.log';

        $entry = json_encode([
            'event' => 'rollback',
            'timestamp' => now()->toIso8601String(),
            'target_release' => $target->name,
            'previous_release' => $previous?->name,
        ]);

        $escapedEntry = escapeshellarg($entry);

        $this->executor->execute("echo {$escapedEntry} >> {$logFile}", throwOnError: false);

        $this->logger->info('Rollback recorded');
    }
}
