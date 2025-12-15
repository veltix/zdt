<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\SshConnectionContract;
use App\Exceptions\RemoteExecutionException;
use App\ValueObjects\CommandResult;
use Psr\Log\LoggerInterface;

final readonly class RemoteExecutor
{
    public function __construct(
        private SshConnectionContract $ssh,
        private LoggerInterface $logger,
    ) {}

    public function execute(string $command, bool $throwOnError = true, ?int $timeout = null): CommandResult
    {
        $this->logger->debug("Executing: {$command}");

        if ($timeout !== null) {
            $result = $this->ssh->execute($command, $timeout);
        } else {
            $result = $this->ssh->execute($command);
        }

        if ($throwOnError && $result->isFailed()) {
            throw new RemoteExecutionException(
                "Command failed with exit code {$result->exitCode}: {$command}\nOutput: {$result->output}"
            );
        }

        return $result;
    }

    public function executeWithRetry(
        string $command,
        int $maxAttempts = 3,
        int $delaySeconds = 5,
        bool $throwOnError = true,
        ?int $timeout = null
    ): CommandResult {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxAttempts) {
            $attempt++;

            try {
                $result = $this->execute($command, $throwOnError, $timeout);

                if ($result->isSuccessful() || ! $throwOnError) {
                    if ($attempt > 1) {
                        $this->logger->info("Command succeeded on attempt {$attempt}");
                    }

                    return $result;
                }
            } catch (RemoteExecutionException $e) {
                $lastException = $e;

                if ($attempt < $maxAttempts) {
                    $this->logger->warning(
                        "Command failed on attempt {$attempt}/{$maxAttempts}, retrying in {$delaySeconds} seconds..."
                    );
                    sleep($delaySeconds);
                } else {
                    $this->logger->error("Command failed after {$maxAttempts} attempts");
                }
            }
        }

        if ($throwOnError && $lastException instanceof RemoteExecutionException) {
            throw $lastException;
        }

        // Should not be reached if throwOnError is true and attempts exhausted
        // But for static analysis / rigorous return:
        return new CommandResult(1, '', $command);
    }

    /**
     * @param  array<int, string>  $commands
     */
    public function executeMultiple(array $commands, bool $throwOnError = true, ?int $timeout = null): void
    {
        foreach ($commands as $command) {
            $this->execute($command, $throwOnError, $timeout);
        }
    }

    public function executeInDirectory(
        string $directory,
        string $command,
        bool $throwOnError = true,
        ?int $timeout = null
    ): CommandResult {
        $fullCommand = "cd {$directory} && {$command}";

        return $this->execute($fullCommand, $throwOnError, $timeout);
    }
}
