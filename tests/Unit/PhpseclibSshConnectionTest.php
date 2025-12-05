<?php

declare(strict_types=1);

use App\Contracts\PhpseclibClientFactoryContract;
use App\Exceptions\SshConnectionException;
use App\Services\PhpseclibSshConnection;
use App\ValueObjects\ServerCredentials;
use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;
use Tests\Helpers\FakeLogger;

beforeEach(function () {
    $this->sshMock = Mockery::mock(SSH2::class);
    $this->sftpMock = Mockery::mock(SFTP::class);
    $this->factoryMock = Mockery::mock(PhpseclibClientFactoryContract::class);
    $this->logger = new FakeLogger();

    // Create a temporary key file for tests that need it
    $this->keyPath = tempnam(sys_get_temp_dir(), 'ssh_key');
    file_put_contents($this->keyPath, 'fake-key-content');

    $this->credentials = new ServerCredentials(
        host: 'test.example.com',
        username: 'testuser',
        keyPath: $this->keyPath,
        port: 22,
        timeout: 30
    );
});

afterEach(function () {
    if (file_exists($this->keyPath)) {
        unlink($this->keyPath);
    }
    Mockery::close();
});

test('connect successfully establishes SSH connection', function () {
    $this->factoryMock->shouldReceive('createSSH2')
        ->with('test.example.com', 22)
        ->once()
        ->andReturn($this->sshMock);

    $this->sshMock->shouldReceive('setTimeout')->with(30)->once();

    $keyObject = new stdClass();
    $this->factoryMock->shouldReceive('loadKey')
        ->with('fake-key-content')
        ->once()
        ->andReturn($keyObject);

    $this->sshMock->shouldReceive('login')
        ->with('testuser', $keyObject)
        ->once()
        ->andReturn(true);

    $connection = new PhpseclibSshConnection($this->credentials, $this->logger, $this->factoryMock);
    $connection->connect();

    expect($this->logger->hasLog('info', 'SSH connection established'))->toBeTrue();
    // We can't check internal state easily, but isConnected checks ssh instance
    // But isConnected also checks ->isConnected() on the mock which we didn't mock yet
});

test('connect throws exception on login failure', function () {
    $this->factoryMock->shouldReceive('createSSH2')->andReturn($this->sshMock);
    $this->sshMock->shouldReceive('setTimeout');
    $this->factoryMock->shouldReceive('loadKey')->andReturn(new stdClass());

    $this->sshMock->shouldReceive('login')->andReturn(false);

    $connection = new PhpseclibSshConnection($this->credentials, $this->logger, $this->factoryMock);

    expect(fn () => $connection->connect())
        ->toThrow(SshConnectionException::class, 'SSH authentication failed');
});

test('connect throws exception on general failure', function () {
    $this->factoryMock->shouldReceive('createSSH2')->andThrow(new Exception('Network error'));

    $connection = new PhpseclibSshConnection($this->credentials, $this->logger, $this->factoryMock);

    expect(fn () => $connection->connect())
        ->toThrow(SshConnectionException::class, 'Failed to establish SSH connection: Network error');
});

test('disconnect explicitly disconnects ssh and sftp', function () {
    // Setup connected state with both SSH and SFTP
    $this->factoryMock->shouldReceive('createSSH2')->andReturn($this->sshMock);
    $this->sshMock->shouldReceive('setTimeout');
    $this->factoryMock->shouldReceive('loadKey')->andReturn(new stdClass());
    $this->sshMock->shouldReceive('login')->andReturn(true);
    $this->sshMock->shouldReceive('isConnected')->andReturn(true);

    $this->factoryMock->shouldReceive('createSFTP')->andReturn($this->sftpMock);
    $this->sftpMock->shouldReceive('login')->andReturn(true);
    $this->sftpMock->shouldReceive('put')->andReturn(true);

    $connection = new PhpseclibSshConnection($this->credentials, $this->logger, $this->factoryMock);
    $connection->connect();
    // Trigger SFTP connection
    $connection->upload('a', 'b');

    // Setup disconnect expectations
    $this->sshMock->shouldReceive('disconnect')->once();
    $this->sftpMock->shouldReceive('disconnect')->once();

    $connection->disconnect();

    expect($this->logger->hasLog('info', 'SSH connection closed'))->toBeTrue();
});

test('upload logs error on failure', function () {
    // Connect SSH & SFTP
    $this->factoryMock->shouldReceive('createSSH2')->andReturn($this->sshMock);
    $this->sshMock->shouldReceive('setTimeout');
    $this->factoryMock->shouldReceive('loadKey')->andReturn(new stdClass());
    $this->sshMock->shouldReceive('login')->andReturn(true);
    $this->sshMock->shouldReceive('isConnected')->andReturn(true);

    $this->factoryMock->shouldReceive('createSFTP')->andReturn($this->sftpMock);
    $this->sftpMock->shouldReceive('login')->andReturn(true);

    $connection = new PhpseclibSshConnection($this->credentials, $this->logger, $this->factoryMock);
    $connection->connect();

    // Upload failure expectation
    $this->sftpMock->shouldReceive('put')->andReturn(false);

    $result = $connection->upload('/local/path', '/remote/path');

    expect($result)->toBeFalse();
    expect($this->logger->hasLog('error', 'Failed to upload file to /remote/path'))->toBeTrue();
});

test('download logs error on failure', function () {
    // Connect SSH & SFTP
    $this->factoryMock->shouldReceive('createSSH2')->andReturn($this->sshMock);
    $this->sshMock->shouldReceive('setTimeout');
    $this->factoryMock->shouldReceive('loadKey')->andReturn(new stdClass());
    $this->sshMock->shouldReceive('login')->andReturn(true);
    $this->sshMock->shouldReceive('isConnected')->andReturn(true);

    $this->factoryMock->shouldReceive('createSFTP')->andReturn($this->sftpMock);
    $this->sftpMock->shouldReceive('login')->andReturn(true);

    $connection = new PhpseclibSshConnection($this->credentials, $this->logger, $this->factoryMock);
    $connection->connect();

    // Download failure expectation
    $this->sftpMock->shouldReceive('get')->andReturn(false);

    $result = $connection->download('/remote/path', '/local/path');

    expect($result)->toBeFalse();
    expect($this->logger->hasLog('error', 'Failed to download file from /remote/path'))->toBeTrue();
});

test('execute runs command successfully', function () {
    // Connect
    $this->factoryMock->shouldReceive('createSSH2')->andReturn($this->sshMock);
    $this->sshMock->shouldReceive('setTimeout')->with(30);
    $this->factoryMock->shouldReceive('loadKey')->andReturn(new stdClass());
    $this->sshMock->shouldReceive('login')->andReturn(true);
    $this->sshMock->shouldReceive('isConnected')->andReturn(true);

    $connection = new PhpseclibSshConnection($this->credentials, $this->logger, $this->factoryMock);
    $connection->connect();

    // Execute with custom timeout
    $this->sshMock->shouldReceive('setTimeout')->with(60)->once();
    $this->sshMock->shouldReceive('exec')->with('ls -la')->once()->andReturn('file1 file2');
    $this->sshMock->shouldReceive('getExitStatus')->once()->andReturn(0);

    $result = $connection->execute('ls -la', 60);

    expect($result->isSuccessful())->toBeTrue();
    expect($result->output)->toBe('file1 file2');
    expect($this->logger->hasLog('debug', 'Executing command: ls -la'))->toBeTrue();
});

test('execute throws exception when not connected', function () {
    $connection = new PhpseclibSshConnection($this->credentials, $this->logger, $this->factoryMock);

    expect(fn () => $connection->execute('test'))
        ->toThrow(SshConnectionException::class, 'Not connected to SSH server');
});

test('upload transmits file successfully', function () {
    // Connect SSH first (implicitly or explicitly)
    $this->factoryMock->shouldReceive('createSSH2')->andReturn($this->sshMock);
    $this->sshMock->shouldReceive('setTimeout');
    $this->factoryMock->shouldReceive('loadKey')->andReturn(new stdClass());
    $this->sshMock->shouldReceive('login')->andReturn(true);
    $this->sshMock->shouldReceive('isConnected')->andReturn(true);

    // Connect SFTP
    $this->factoryMock->shouldReceive('createSFTP')
        ->with('test.example.com', 22)
        ->once()
        ->andReturn($this->sftpMock);

    $this->sftpMock->shouldReceive('login')->andReturn(true);

    $connection = new PhpseclibSshConnection($this->credentials, $this->logger, $this->factoryMock);
    $connection->connect();

    // Upload expectation
    $this->sftpMock->shouldReceive('put')
        ->with('/remote/path', '/local/path', SFTP::SOURCE_LOCAL_FILE)
        ->once()
        ->andReturn(true);

    $result = $connection->upload('/local/path', '/remote/path');

    expect($result)->toBeTrue();
    expect($this->logger->hasLog('info', 'Successfully uploaded file'))->toBeTrue();
});

test('download transmits file successfully', function () {
    // Connect SSH & SFTP
    $this->factoryMock->shouldReceive('createSSH2')->andReturn($this->sshMock);
    $this->sshMock->shouldReceive('setTimeout');
    $this->factoryMock->shouldReceive('loadKey')->andReturn(new stdClass());
    $this->sshMock->shouldReceive('login')->andReturn(true);
    $this->sshMock->shouldReceive('isConnected')->andReturn(true);

    $this->factoryMock->shouldReceive('createSFTP')->andReturn($this->sftpMock);
    $this->sftpMock->shouldReceive('login')->andReturn(true);

    $connection = new PhpseclibSshConnection($this->credentials, $this->logger, $this->factoryMock);
    $connection->connect();

    // Download expectation
    $this->sftpMock->shouldReceive('get')
        ->with('/remote/path', '/local/path')
        ->once()
        ->andReturn(true);

    $result = $connection->download('/remote/path', '/local/path');

    expect($result)->toBeTrue();
    expect($this->logger->hasLog('info', 'Successfully downloaded file'))->toBeTrue();
});

test('fileExists checks via sftp', function () {
    // Connect SSH & SFTP
    $this->factoryMock->shouldReceive('createSSH2')->andReturn($this->sshMock);
    $this->sshMock->shouldReceive('setTimeout');
    $this->factoryMock->shouldReceive('loadKey')->andReturn(new stdClass());
    $this->sshMock->shouldReceive('login')->andReturn(true);
    $this->sshMock->shouldReceive('isConnected')->andReturn(true);

    $this->factoryMock->shouldReceive('createSFTP')->andReturn($this->sftpMock);
    $this->sftpMock->shouldReceive('login')->andReturn(true);

    $connection = new PhpseclibSshConnection($this->credentials, $this->logger, $this->factoryMock);
    $connection->connect();

    $this->sftpMock->shouldReceive('file_exists')->with('/path')->once()->andReturn(true);

    expect($connection->fileExists('/path'))->toBeTrue();
});

test('directoryExists checks via sftp', function () {
    // Connect SSH & SFTP
    $this->factoryMock->shouldReceive('createSSH2')->andReturn($this->sshMock);
    $this->sshMock->shouldReceive('setTimeout');
    $this->factoryMock->shouldReceive('loadKey')->andReturn(new stdClass());
    $this->sshMock->shouldReceive('login')->andReturn(true);
    $this->sshMock->shouldReceive('isConnected')->andReturn(true);

    $this->factoryMock->shouldReceive('createSFTP')->andReturn($this->sftpMock);
    $this->sftpMock->shouldReceive('login')->andReturn(true);

    $connection = new PhpseclibSshConnection($this->credentials, $this->logger, $this->factoryMock);
    $connection->connect();

    $this->sftpMock->shouldReceive('is_dir')->with('/path')->once()->andReturn(true);

    expect($connection->directoryExists('/path'))->toBeTrue();
});

test('ensureSftpConnected throws exception on SFTP login failure', function () {
    // Connect SSH & SFTP
    $this->factoryMock->shouldReceive('createSSH2')->andReturn($this->sshMock);
    $this->sshMock->shouldReceive('setTimeout');
    $this->factoryMock->shouldReceive('loadKey')->andReturn(new stdClass());
    $this->sshMock->shouldReceive('login')->andReturn(true);
    $this->sshMock->shouldReceive('isConnected')->andReturn(true);

    $this->factoryMock->shouldReceive('createSFTP')->andReturn($this->sftpMock);
    $this->sftpMock->shouldReceive('login')->andReturn(false); // Fail login

    $connection = new PhpseclibSshConnection($this->credentials, $this->logger, $this->factoryMock);
    $connection->connect();

    expect(fn () => $connection->upload('a', 'b'))
        ->toThrow(SshConnectionException::class, 'SFTP authentication failed');
});

test('isConnected returns false when not connected', function () {
    $connection = new PhpseclibSshConnection($this->credentials, $this->logger, $this->factoryMock);
    expect($connection->isConnected())->toBeFalse();
});
