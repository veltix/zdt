<?php

declare(strict_types=1);

namespace App\Commands;

use App\Actions\EstablishSshConnection;
use App\Actions\PruneFailedReleases;
use App\Actions\RemoveOldReleases;
use App\Actions\ValidateDeploymentConfig;
use App\Contracts\SshConnectionContract;
use LaravelZero\Framework\Commands\Command;
use Throwable;

final class CleanupCommand extends Command
{
    protected $signature = 'releases:cleanup {--config=deploy.php} {--keep=5}';

    protected $description = 'Remove old releases from remote server';

    public function handle(
        ValidateDeploymentConfig $validateConfig,
        EstablishSshConnection $establishConnection,
        SshConnectionContract $ssh,
        RemoveOldReleases $removeOld,
        PruneFailedReleases $pruneFailed,
    ): int {
        $this->info('Cleaning up old releases...');
        $this->newLine();

        try {
            $config = $validateConfig->handle($this->option('config'));

            if ($this->option('keep')) {
                $config = new \App\ValueObjects\DeploymentConfig(
                    server: $config->server,
                    repository: $config->repository,
                    paths: $config->paths,
                    options: array_merge($config->options, ['keep_releases' => (int) $this->option('keep')]),
                    hooks: $config->hooks,
                    healthCheck: $config->healthCheck,
                );
            }

            $this->line("Target: {$config->server['host']}");
            $this->line("Keeping: {$config->getKeepReleases()} releases");
            $this->newLine();

            $this->task('Connecting to server', function () use ($establishConnection, $ssh, $config): void {
                $establishConnection->handle($ssh, $config);
            });

            $this->task('Pruning failed releases', function () use ($pruneFailed, $config): void {
                $pruneFailed->handle($config);
            });

            $this->task('Removing old releases', function () use ($removeOld, $config): void {
                $removeOld->handle($config);
            });

            $ssh->disconnect();

            $this->newLine();
            $this->info('Cleanup completed successfully!');
            $this->newLine();

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Cleanup failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
