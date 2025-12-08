<?php

declare(strict_types=1);

namespace App\Commands;

use App\Actions\AcquireDeploymentLock;
use App\Actions\BackupDatabase;
use App\Actions\CheckoutBranch;
use App\Actions\CloneRepository;
use App\Actions\CompileAssets;
use App\Actions\EstablishSshConnection;
use App\Actions\ExecuteRollback;
use App\Actions\IdentifyRollbackTarget;
use App\Actions\InstallComposerDependencies;
use App\Actions\LinkCustomSharedPaths;
use App\Actions\LinkSharedStorage;
use App\Actions\OptimizeApplication;
use App\Actions\PerformHealthCheck;
use App\Actions\PrepareReleaseDirectory;
use App\Actions\RecordDeployment;
use App\Actions\RecordRollback;
use App\Actions\ReleaseDeploymentLock;
use App\Actions\RemoveOldReleases;
use App\Actions\RunDatabaseMigrations;
use App\Actions\SendDeploymentNotification;
use App\Actions\SyncEnvironmentFile;
use App\Actions\UpdateCurrentSymlink;
use App\Actions\ValidateDeploymentConfig;
use App\Actions\ValidateNewRelease;
use App\Actions\ValidateRollbackTarget;
use App\Actions\ValidateServerRequirements;
use App\Actions\WarmCaches;
use App\Contracts\SshConnectionContract;
use LaravelZero\Framework\Commands\Command;
use Throwable;

final class DeployCommand extends Command
{
    protected $signature = 'deploy {--config=deploy.php} {--branch=}';

    protected $description = 'Deploy application to remote server with zero downtime';

    public function handle(
        ValidateDeploymentConfig $validateConfig,
        EstablishSshConnection $establishConnection,
        SshConnectionContract $ssh,
        ValidateServerRequirements $validateRequirements,
        AcquireDeploymentLock $acquireLock,
        ReleaseDeploymentLock $releaseLock,
        PrepareReleaseDirectory $prepareRelease,
        CloneRepository $cloneRepo,
        CheckoutBranch $checkoutBranch,
        SyncEnvironmentFile $syncEnv,
        LinkSharedStorage $linkStorage,
        LinkCustomSharedPaths $linkCustomPaths,
        InstallComposerDependencies $installComposer,
        CompileAssets $compileAssets,
        BackupDatabase $backupDatabase,
        RunDatabaseMigrations $runMigrations,
        OptimizeApplication $optimize,
        ValidateNewRelease $validateRelease,
        UpdateCurrentSymlink $updateSymlink,
        PerformHealthCheck $performHealthCheck,
        SendDeploymentNotification $notify,
        RecordDeployment $recordDeployment,
        WarmCaches $warmCaches,
        RemoveOldReleases $cleanup,
        IdentifyRollbackTarget $identifyRollback,
        ValidateRollbackTarget $validateRollback,
        ExecuteRollback $executeRollback,
        RecordRollback $recordRollback,
    ): int {
        $this->info('🚀 Starting zero downtime deployment...');
        $this->newLine();

        $release = null;
        $config = null;

        try {
            $this->runTask('Loading configuration', function () use ($validateConfig, &$config): void {
                $config = $validateConfig->handle($this->option('config'));
            });

            if ($this->option('branch')) {
                $config = new \App\ValueObjects\DeploymentConfig(
                    server: $config->server,
                    repository: array_merge($config->repository, ['branch' => $this->option('branch')]),
                    paths: $config->paths,
                    options: $config->options,
                    hooks: $config->hooks,
                    healthCheck: $config->healthCheck,
                    sharedPaths: $config->sharedPaths,
                    database: $config->database,
                    notifications: $config->notifications,
                );
            }

            $this->line("Target: {$config->server['host']}");
            $this->line("Repository: {$config->getRepositoryUrl()}");
            $this->line("Branch: {$config->getBranch()}");
            $this->newLine();

            $this->runTask('Connecting to server', function () use ($establishConnection, $ssh, $config): void {
                $establishConnection->handle($ssh, $config);
            });

            $this->runTask('Validating server requirements', function () use ($validateRequirements, $config): void {
                $validateRequirements->handle($config);
            });

            $this->runTask('Acquiring deployment lock', function () use ($acquireLock, $config): void {
                $acquireLock->handle($config);
            });

            $this->runTask('Preparing release directory', function () use ($prepareRelease, $config, &$release): void {
                $release = $prepareRelease->handle($config);
            });

            $this->line("Release: {$release->name}");

            $notify->handle($config, $release, 'started');
            $this->newLine();

            $this->runTask('Cloning repository', function () use ($cloneRepo, $config, $release): void {
                $cloneRepo->handle($config, $release);
            });

            $this->runTask('Checking out branch', function () use ($checkoutBranch, $config, &$release): void {
                $release = $checkoutBranch->handle($config, $release);
            });

            $this->line("Commit: {$release->commitHash}");
            $this->newLine();

            $this->runTask('Syncing environment file', function () use ($syncEnv, $config, $release): void {
                $syncEnv->handle($config, $release);
            });

            $this->runTask('Linking shared storage', function () use ($linkStorage, $config, $release): void {
                $linkStorage->handle($config, $release);
            });

            $this->runTask('Linking custom shared paths', function () use ($linkCustomPaths, $config, $release): void {
                $linkCustomPaths->handle($config, $release);
            });

            if ($config->shouldUseComposer()) {
                $this->runTask('Installing Composer dependencies', function () use ($installComposer, $config, $release): void {
                    $installComposer->handle($config, $release);
                });
            }

            if ($config->shouldUseNpm()) {
                $this->runTask('Compiling assets', function () use ($compileAssets, $config, $release): void {
                    $compileAssets->handle($config, $release);
                });
            }

            if ($config->shouldBackupDatabase()) {
                $this->runTask('Backing up database', function () use ($backupDatabase, $config, $release): void {
                    $backupDatabase->handle($config, $release);
                });
            }

            if ($config->shouldRunMigrations()) {
                $this->runTask('Running database migrations', function () use ($runMigrations, $config, $release): void {
                    $runMigrations->handle($config, $release);
                });
            }

            $this->runTask('Optimizing application', function () use ($optimize, $config, $release): void {
                $optimize->handle($config, $release);
            });

            $this->runTask('Validating new release', function () use ($validateRelease, $config, $release): void {
                $validateRelease->handle($config, $release);
            });

            $this->runTask('Activating release', function () use ($updateSymlink, $config, $release): void {
                $updateSymlink->handle($config, $release);
            });

            $this->info('Release activated - ZERO DOWNTIME!');
            $this->newLine();

            $this->runTask('Performing health check', function () use ($performHealthCheck, $config, $release): void {
                $performHealthCheck->handle($config, $release);
            });

            $this->runTask('Warming caches', function () use ($warmCaches, $config, $release): void {
                $warmCaches->handle($config, $release);
            });

            $this->runTask('Recording deployment', function () use ($recordDeployment, $config, $release): void {
                $recordDeployment->handle($config, $release);
            });

            $this->runTask('Cleaning up old releases', function () use ($cleanup, $config): void {
                $cleanup->handle($config);
            });

            $notify->handle($config, $release, 'success');

            $this->newLine();
            $this->info('Deployment completed successfully!');
            $this->newLine();

            return self::SUCCESS;
        } catch (Throwable $e) {
            if ($release !== null && $config !== null) {
                try {
                    $notify->handle($config, $release, 'failed', $e->getMessage());
                } catch (Throwable $notifyError) {
                    // Ignore notification error
                }
            }

            $this->newLine();
            $this->error('Deployment failed: '.$e->getMessage());
            $this->newLine();

            if ($release !== null && $this->confirm('Attempt automatic rollback?', true)) {
                try {
                    $this->warn('Rolling back to previous release...');

                    $target = $identifyRollback->handle($config);
                    $validateRollback->handle($target);
                    $executeRollback->handle($config, $target);
                    $recordRollback->handle($config, $target, $release);

                    $this->info('Rollback completed');
                } catch (Throwable $rollbackError) {
                    $this->error('Rollback failed: '.$rollbackError->getMessage());
                }
            }

            return self::FAILURE;
        } finally {
            if ($config !== null) {
                try {
                    $releaseLock->handle($config);
                    $ssh->disconnect();
                } catch (Throwable $e) {
                    // Ignore cleanup errors
                }
            }
        }
    }

    /**
     * Run a task and ensure exceptions are rethrown.
     *
     * @throws Throwable
     */
    private function runTask(string $title, callable $task): void
    {
        $exception = null;

        $result = $this->task($title, function () use ($task, &$exception) {
            try {
                $task();

                return true;
            } catch (Throwable $e) {
                $exception = $e;

                return false;
            }
        });

        if ($exception) {
            throw $exception;
        }

    }
}
