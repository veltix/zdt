<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Contracts\SshConnectionContract;
use App\Exceptions\SshConnectionException;
use App\ValueObjects\CommandResult;
use App\ValueObjects\ServerCredentials;

final class FakeSshConnection implements SshConnectionContract
{
    /** @var array<string, CommandResult|callable> */
    public array $commandResults = [];

    /** @var array<string, string> */
    public array $executedCommands = [];

    /** @var array<int, int|null> */
    public array $executedTimeouts = [];

    /** @var array<string, bool> */
    public array $files = [];

    /** @var array<string, bool> */
    public array $directories = [];

    /** @var array<string, string> */
    public array $uploadedFiles = [];

    /** @var array<string, string> */
    public array $downloadedFiles = [];

    public bool $failsUpload = false;

    private bool $connected = false;

    public function __construct(
        private readonly ?ServerCredentials $credentials = null,
    ) {}

    public function connect(): void
    {
        if ($this->credentials !== null && $this->credentials->host === 'fail.test') {
            throw new SshConnectionException('Connection failed');
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
        if (! $this->connected) {
            throw new SshConnectionException('Not connected to SSH server. Call connect() first.');
        }

        $this->executedCommands[] = $command;
        $this->executedTimeouts[] = $timeout;

        // Return pre-configured result if exists
        if (isset($this->commandResults[$command])) {
            $result = $this->commandResults[$command];

            // Support callable for dynamic behavior (e.g., retry testing)
            if (is_callable($result)) {
                return $result();
            }

            return $result;
        }

        // Default successful execution
        return new CommandResult(
            exitCode: 0,
            output: "Executed: {$command}",
            command: $command,
        );
    }

    public function upload(string $localPath, string $remotePath): bool
    {
        if (! $this->connected) {
            throw new SshConnectionException('Not connected to SSH server. Call connect() first.');
        }

        if ($this->failsUpload) {
            return false;
        }

        $this->uploadedFiles[$remotePath] = $localPath;
        $this->files[$remotePath] = true;

        return true;
    }

    public function download(string $remotePath, string $localPath): bool
    {
        if (! $this->connected) {
            throw new SshConnectionException('Not connected to SSH server. Call connect() first.');
        }

        if (! isset($this->files[$remotePath])) {
            return false;
        }

        $this->downloadedFiles[$localPath] = $remotePath;

        return true;
    }

    public function fileExists(string $path): bool
    {
        if (! $this->connected) {
            throw new SshConnectionException('Not connected to SSH server. Call connect() first.');
        }

        return $this->files[$path] ?? false;
    }

    public function directoryExists(string $path): bool
    {
        if (! $this->connected) {
            throw new SshConnectionException('Not connected to SSH server. Call connect() first.');
        }

        return $this->directories[$path] ?? false;
    }

    public function setCommandResult(string $command, CommandResult $result): void
    {
        $this->commandResults[$command] = $result;
    }

    public function setFileExists(string $path, bool $exists = true): void
    {
        $this->files[$path] = $exists;
    }

    public function setDirectoryExists(string $path, bool $exists = true): void
    {
        $this->directories[$path] = $exists;
    }
}
