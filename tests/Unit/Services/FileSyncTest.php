<?php

declare(strict_types=1);

use App\Exceptions\FileSyncException;
use App\Services\FileSync;
use App\Services\RemoteExecutor;
use Tests\Helpers\FakeLogger;
use Tests\Helpers\FakeSshConnection;

beforeEach(function () {
    $this->ssh = new FakeSshConnection();
    $this->ssh->connect();
    $this->logger = new FakeLogger();
    $this->executor = new RemoteExecutor($this->ssh, $this->logger);
    $this->fileSync = new FileSync($this->ssh, $this->executor, $this->logger);

    // Create temporary test file
    $this->tempFile = tempnam(sys_get_temp_dir(), 'test');
    file_put_contents($this->tempFile, 'test content');
});

afterEach(function () {
    if (file_exists($this->tempFile)) {
        unlink($this->tempFile);
    }
});

test('syncEnvironmentFile uploads env file successfully', function () {
    $this->fileSync->syncEnvironmentFile($this->tempFile, '/var/www/.env');

    expect($this->ssh->uploadedFiles)->toHaveKey('/var/www/.env')
        ->and($this->ssh->uploadedFiles['/var/www/.env'])->toBe($this->tempFile)
        ->and($this->ssh->executedCommands)->toContain('chmod 600 /var/www/.env');
});

test('syncEnvironmentFile throws exception when local file not found', function () {
    expect(fn () => $this->fileSync->syncEnvironmentFile('/nonexistent/file', '/var/www/.env'))
        ->toThrow(FileSyncException::class, 'Environment file not found');
});

test('uploadFile uploads file with default permissions', function () {
    $this->fileSync->uploadFile($this->tempFile, '/var/www/file.txt');

    expect($this->ssh->uploadedFiles)->toHaveKey('/var/www/file.txt')
        ->and($this->ssh->executedCommands)->toContain('chmod 644 /var/www/file.txt');
});

test('uploadFile uploads file with custom permissions', function () {
    $this->fileSync->uploadFile($this->tempFile, '/var/www/file.txt', 0755);

    expect($this->ssh->uploadedFiles)->toHaveKey('/var/www/file.txt')
        ->and($this->ssh->executedCommands)->toContain('chmod 755 /var/www/file.txt');
});

test('uploadFile throws exception when local file not found', function () {
    expect(fn () => $this->fileSync->uploadFile('/nonexistent/file', '/var/www/file.txt'))
        ->toThrow(FileSyncException::class, 'Local file not found');
});

test('downloadFile downloads file successfully', function () {
    $this->ssh->setFileExists('/var/www/file.txt', true);

    $this->fileSync->downloadFile('/var/www/file.txt', '/tmp/local.txt');

    expect($this->ssh->downloadedFiles)->toHaveKey('/tmp/local.txt');
});

test('downloadFile throws exception when download fails', function () {
    // The FakeSshConnection will return false for download when file doesn't exist
    // (fileExists check happens in download method)

    expect(fn () => $this->fileSync->downloadFile('/var/www/nonexistent.txt', '/tmp/local.txt'))
        ->toThrow(FileSyncException::class, 'Failed to download file');
});

test('createSymlink creates atomic symlink', function () {
    // createSymlink($target, $link) - creates symlink $link pointing to $target
    $this->fileSync->createSymlink('/var/www/releases/123', '/var/www/current');

    expect($this->ssh->executedCommands)->toContain('ln -nfs /var/www/releases/123 /var/www/current.tmp')
        ->and($this->ssh->executedCommands)->toContain('mv -Tf /var/www/current.tmp /var/www/current');
});

test('copySharedFile copies file from shared to release', function () {
    $this->fileSync->copySharedFile('/var/www/shared/.env', '/var/www/releases/123/.env');

    expect($this->ssh->executedCommands)->toContain('cp /var/www/shared/.env /var/www/releases/123/.env');
});

test('syncEnvironmentFile throws exception when upload fails', function () {
    $this->ssh->failsUpload = true;

    expect(fn () => $this->fileSync->syncEnvironmentFile($this->tempFile, '/var/www/.env'))
        ->toThrow(FileSyncException::class, 'Failed to upload environment file');
});

test('uploadFile throws exception when upload fails', function () {
    $this->ssh->failsUpload = true;

    expect(fn () => $this->fileSync->uploadFile($this->tempFile, '/var/www/file.txt'))
        ->toThrow(FileSyncException::class, 'Failed to upload file');
});
