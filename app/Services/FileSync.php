<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\SshConnectionContract;
use App\Exceptions\FileSyncException;
use Psr\Log\LoggerInterface;

final readonly class FileSync
{
    public function __construct(
        private SshConnectionContract $ssh,
        private RemoteExecutor $executor,
        private LoggerInterface $logger,
    ) {}

    public function syncEnvironmentFile(string $localPath, string $remotePath): void
    {
        if (! file_exists($localPath)) {
            throw new FileSyncException("Environment file not found: {$localPath}");
        }

        $this->logger->info("Syncing .env file to {$remotePath}");

        if (! $this->ssh->upload($localPath, $remotePath)) {
            throw new FileSyncException("Failed to upload environment file to {$remotePath}");
        }

        $this->executor->execute("chmod 600 {$remotePath}", throwOnError: false);
    }

    public function uploadFile(string $localPath, string $remotePath, int $permissions = 0644): void
    {
        if (! file_exists($localPath)) {
            throw new FileSyncException("Local file not found: {$localPath}");
        }

        $this->logger->debug("Uploading file to {$remotePath}");

        if (! $this->ssh->upload($localPath, $remotePath)) {
            throw new FileSyncException("Failed to upload file to {$remotePath}");
        }

        $this->executor->execute(
            sprintf('chmod %o %s', $permissions, $remotePath),
            throwOnError: false
        );
    }

    public function downloadFile(string $remotePath, string $localPath): void
    {
        $this->logger->debug("Downloading file from {$remotePath}");

        if (! $this->ssh->download($remotePath, $localPath)) {
            throw new FileSyncException("Failed to download file from {$remotePath}");
        }
    }

    public function createSymlink(string $target, string $link): void
    {
        $this->logger->debug("Creating symlink: {$link} -> {$target}");

        // Create temporary symlink first for atomic operation
        $tempLink = $link.'.tmp';

        $this->executor->execute("ln -nfs {$target} {$tempLink}");

        // Atomic rename to final symlink
        $this->executor->execute("mv -Tf {$tempLink} {$link}");

        $this->logger->info("Symlink created: {$link} -> {$target}");
    }

    public function copySharedFile(string $sharedPath, string $releasePath): void
    {
        $this->logger->debug("Copying from shared: {$sharedPath} to {$releasePath}");

        $this->executor->execute("cp {$sharedPath} {$releasePath}");
    }
}
