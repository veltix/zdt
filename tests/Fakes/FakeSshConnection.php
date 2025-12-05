<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Contracts\SshConnectionContract;
use App\ValueObjects\CommandResult;
use RuntimeException;
use Throwable;

final class FakeSshConnection implements SshConnectionContract
{
    public array $commands = [];

    public array $uploaded = [];

    public array $downloaded = [];

    public array $commandResponses = [];

    public array $fileExistenceMap = [];

    public array $directoryExistenceMap = [];

    public bool $connected = false;

    public bool $failOnConnect = false;

    public function connect(): void
    {
        if ($this->failOnConnect) {
            throw new \App\Exceptions\SshConnectionException('Connection failed');
        }

        $this->connected = true;
    }

    public function disconnect(): void
    {
        $this->connected = false;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function execute(string $command, ?int $timeout = 300): CommandResult
    {
        $this->commands[] = $command;

        foreach ($this->commandResponses as $pattern => $response) {
            if ($pattern === $command || @preg_match($pattern, $command)) {
                if ($response instanceof Throwable) {
                    throw $response;
                }
                if ($response instanceof CommandResult) {
                    return $response;
                }

                // Assume string is output for success
                return new CommandResult(
                    exitCode: 0,
                    output: (string) $response,
                    command: $command
                );
            }
        }

        // Default success
        return new CommandResult(
            exitCode: 0,
            output: '',
            command: $command
        );
    }

    public function upload(string $localPath, string $remotePath): bool
    {
        $this->uploaded[$localPath] = $remotePath;

        return true;
    }

    public function download(string $remotePath, string $localPath): bool
    {
        $this->downloaded[$remotePath] = $localPath;

        return true;
    }

    public function fileExists(string $path): bool
    {
        return $this->fileExistenceMap[$path] ?? false;
    }

    public function directoryExists(string $path): bool
    {
        return $this->directoryExistenceMap[$path] ?? false;
    }

    // -- Test Helper Methods --

    public function failCommand(string $commandPattern, int $exitCode = 1, string $output = 'Error'): void
    {
        $this->commandResponses[$commandPattern] = new CommandResult($exitCode, $output, 'mocked-command');
    }

    public function throwOnCommand(string $commandPattern, Throwable $exception): void
    {
        $this->commandResponses[$commandPattern] = $exception;
    }

    public function expectFileExists(string $path, bool $exists = true): void
    {
        $this->fileExistenceMap[$path] = $exists;
    }

    public function expectDirectoryExists(string $path, bool $exists = true): void
    {
        $this->directoryExistenceMap[$path] = $exists;
    }

    public function assertCommandExecuted(string $pattern): void
    {
        foreach ($this->commands as $cmd) {
            if (str_contains($cmd, $pattern) || @preg_match($pattern, $cmd)) {
                return;
            }
        }

        throw new RuntimeException("Expected command matching '{$pattern}' was not executed. Executed commands: ".implode(', ', $this->commands));
    }
}
