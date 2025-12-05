<?php

declare(strict_types=1);

namespace App\Commands;

use App\Actions\EstablishSshConnection;
use App\Actions\ExecuteRollback;
use App\Actions\IdentifyRollbackTarget;
use App\Actions\RecordRollback;
use App\Actions\ValidateDeploymentConfig;
use App\Actions\ValidateRollbackTarget;
use App\Contracts\SshConnectionContract;
use LaravelZero\Framework\Commands\Command;
use Throwable;

final class RollbackCommand extends Command
{
    protected $signature = 'rollback {--config=deploy.php} {--release=}';

    protected $description = 'Rollback to a previous release';

    public function handle(
        ValidateDeploymentConfig $validateConfig,
        EstablishSshConnection $establishConnection,
        SshConnectionContract $ssh,
        IdentifyRollbackTarget $identifyTarget,
        ValidateRollbackTarget $validateTarget,
        ExecuteRollback $executeRollback,
        RecordRollback $recordRollback,
    ): int {
        $this->warn('Starting rollback...');
        $this->newLine();

        try {
            $config = $this->runTask('Loading configuration', fn (): \App\ValueObjects\DeploymentConfig => $validateConfig->handle($this->option('config')));

            $this->line("Target server: {$config->server['host']}");
            $this->newLine();

            $this->runTask('Connecting to server', function () use ($establishConnection, $ssh, $config): void {
                $establishConnection->handle($ssh, $config);
            });

            $target = $this->runTask('Identifying rollback target', fn (): \App\ValueObjects\Release => $identifyTarget->handle($config, $this->option('release')));

            $this->line("Rolling back to: {$target->name}");
            $this->newLine();

            $this->runTask('Validating rollback target', function () use ($validateTarget, $target): void {
                $validateTarget->handle($target);
            });

            $this->runTask('Executing rollback', function () use ($executeRollback, $config, $target): void {
                $executeRollback->handle($config, $target);
            });

            $this->runTask('Recording rollback', function () use ($recordRollback, $config, $target): void {
                $recordRollback->handle($config, $target);
            });

            $ssh->disconnect();

            $this->newLine();
            $this->info('Rollback completed successfully!');
            $this->newLine();

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->newLine();
            $this->error('Rollback failed: '.$e->getMessage());
            $this->newLine();

            return self::FAILURE;
        }
    }

    /**
     * Run a task and ensure exceptions are rethrown.
     *
     * @template T
     *
     * @param  callable(): T  $task
     * @return T
     *
     * @throws Throwable
     */
    private function runTask(string $title, callable $task): mixed
    {
        $exception = null;
        $resultValue = null;

        $success = $this->task($title, function () use ($task, &$exception, &$resultValue) {
            try {
                $resultValue = $task();

                return true;
            } catch (Throwable $e) {
                $exception = $e;

                return false;
            }
        });

        if ($exception) {
            throw $exception;
        }

        return $resultValue;
    }
}
