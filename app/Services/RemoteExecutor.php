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

    public function execute(string $command, bool $throwOnError = true): CommandResult
    {
        $this->logger->debug("Executing: {$command}");

        $result = $this->ssh->execute($command);

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
        bool $throwOnError = true
    ): CommandResult {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxAttempts) {
            $attempt++;

            try {
                $result = $this->execute($command, $throwOnError);

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
    }

    /**
     * @param  array<int, string>  $commands
     */
    public function executeMultiple(array $commands, bool $throwOnError = true): void
    {
        foreach ($commands as $command) {
            $this->execute($command, $throwOnError);
        }
    }

    public function executeInDirectory(string $directory, string $command, bool $throwOnError = true): CommandResult
    {
        $fullCommand = "cd {$directory} && {$command}";

        return $this->execute($fullCommand, $throwOnError);
    }
}
