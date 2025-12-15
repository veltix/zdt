<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\PhpseclibClientFactoryContract;
use App\Contracts\SshConnectionContract;
use App\Exceptions\SshConnectionException;
use App\ValueObjects\CommandResult;
use App\ValueObjects\ServerCredentials;
use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;
use Psr\Log\LoggerInterface;
use Throwable;

final class PhpseclibSshConnection implements SshConnectionContract
{
    private ?SSH2 $ssh = null;

    private ?SFTP $sftp = null;

    public function __construct(
        private readonly ServerCredentials $credentials,
        private readonly LoggerInterface $logger,
        private readonly PhpseclibClientFactoryContract $factory = new PhpseclibFactories\PhpseclibClientFactory(),
    ) {}

    public function connect(): void
    {
        try {
            $this->ssh = $this->factory->createSSH2($this->credentials->host, $this->credentials->port);
            $this->ssh->setTimeout($this->credentials->timeout);
            $this->ssh->setKeepAlive(10);

            $keyContent = $this->getFileContents($this->credentials->keyPath);
            $key = $this->factory->loadKey($keyContent);

            if (! $this->ssh->login($this->credentials->username, $key)) {
                throw new SshConnectionException(
                    "SSH authentication failed for {$this->credentials->username}@{$this->credentials->host}"
                );
            }

            $this->logger->info('SSH connection established', [
                'host' => $this->credentials->host,
                'username' => $this->credentials->username,
            ]);
        } catch (Throwable $e) {
            throw new SshConnectionException(
                "Failed to establish SSH connection: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    public function disconnect(): void
    {
        if ($this->ssh instanceof SSH2) {
            $this->ssh->disconnect();
            $this->ssh = null;
        }

        if ($this->sftp instanceof SFTP) {
            $this->sftp->disconnect();
            $this->sftp = null;
        }

        $this->logger->info('SSH connection closed');
    }

    public function execute(string $command, ?int $timeout = 300): CommandResult
    {
        $this->ensureConnected();

        if ($timeout !== null) {
            $this->ssh->setTimeout($timeout);
        }

        $this->logger->debug("Executing command: {$command}");

        $output = $this->ssh->exec($command);
        $exitCode = $this->ssh->getExitStatus();

        // In case getExitStatus is false/null? It usually returns int.
        // But phpseclib doc says: "If the command was not successful then false is returned."
        // Wait, exec returns string|bool.
        // getExitStatus returns int|bool.

        $exitCode = (int) $exitCode;

        $this->logger->debug("Command completed with exit code: {$exitCode}");

        return new CommandResult(
            exitCode: $exitCode,
            output: (string) $output,
            command: $command,
        );
    }

    public function upload(string $localPath, string $remotePath): bool
    {
        $this->ensureSftpConnected();

        $this->logger->debug("Uploading {$localPath} to {$remotePath}");

        $result = $this->sftp->put($remotePath, $localPath, SFTP::SOURCE_LOCAL_FILE);

        if ($result) {
            $this->logger->info("Successfully uploaded file to {$remotePath}");
        } else {
            $this->logger->error("Failed to upload file to {$remotePath}");
        }

        return $result;
    }

    public function download(string $remotePath, string $localPath): bool
    {
        $this->ensureSftpConnected();

        $this->logger->debug("Downloading {$remotePath} to {$localPath}");

        $result = $this->sftp->get($remotePath, $localPath);

        if ($result) {
            $this->logger->info("Successfully downloaded file from {$remotePath}");
        } else {
            $this->logger->error("Failed to download file from {$remotePath}");
        }

        return $result !== false;
    }

    public function fileExists(string $path): bool
    {
        $this->ensureSftpConnected();

        return $this->sftp->file_exists($path);
    }

    public function directoryExists(string $path): bool
    {
        $this->ensureSftpConnected();

        return $this->sftp->is_dir($path);
    }

    public function isConnected(): bool
    {
        return $this->ssh instanceof SSH2 && $this->ssh->isConnected();
    }

    private function ensureConnected(): void
    {
        if (! $this->isConnected()) {
            throw new SshConnectionException('Not connected to SSH server. Call connect() first.');
        }
    }

    private function ensureSftpConnected(): void
    {
        $this->ensureConnected();

        if (! $this->sftp instanceof SFTP) {
            $this->sftp = $this->factory->createSFTP($this->credentials->host, $this->credentials->port);

            $keyContent = $this->getFileContents($this->credentials->keyPath);
            $key = $this->factory->loadKey($keyContent);

            if (! $this->sftp->login($this->credentials->username, $key)) {
                throw new SshConnectionException('SFTP authentication failed');
            }
        }
    }

    /**
     * @codeCoverageIgnore
     */
    private function getFileContents(string $path): string
    {
        if (! file_exists($path)) {
            throw new SshConnectionException("Key file not found: {$path}");
        }
        $content = file_get_contents($path);
        if ($content === false) {
            throw new SshConnectionException("Failed to read key file: {$path}");
        }

        return $content;
    }
}
