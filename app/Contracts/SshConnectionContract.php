<?php

declare(strict_types=1);

namespace App\Contracts;

use App\ValueObjects\CommandResult;

interface SshConnectionContract
{
    public function connect(): void;

    public function disconnect(): void;

    public function execute(string $command, ?int $timeout = 300): CommandResult;

    public function upload(string $localPath, string $remotePath): bool;

    public function download(string $remotePath, string $localPath): bool;

    public function fileExists(string $path): bool;

    public function directoryExists(string $path): bool;

    public function isConnected(): bool;
}
