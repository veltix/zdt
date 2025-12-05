<?php

declare(strict_types=1);

use App\Actions\BackupDatabase;
use App\Exceptions\DatabaseBackupException;
use App\Services\RemoteExecutor;
use App\ValueObjects\CommandResult;
use App\ValueObjects\DeploymentConfig;
use App\ValueObjects\Release;
use Psr\Log\NullLogger;
use Tests\Helpers\SshMockHelper;

beforeEach(function () {
    $this->logger = new NullLogger;
    $this->release = new Release('20231215-143000', '/var/www/app/releases/20231215-143000', new DateTimeImmutable);
});

test('database backup validates disabled state', function () {
    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: [],
        hooks: [],
        healthCheck: [],
        sharedPaths: [],
        database: ['backup_enabled' => false],
        notifications: [],
    );

    expect($config->shouldBackupDatabase())->toBeFalse();
});

test('database backup validates configuration', function () {
    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: [],
        hooks: [],
        healthCheck: [],
        sharedPaths: [],
        database: [
            'backup_enabled' => true,
            'connection' => 'mysql',
            'host' => 'db.example.com',
            'port' => 3306,
            'database' => 'myapp',
            'username' => 'dbuser',
            'password' => 'dbpass',
            'keep_backups' => 10,
        ],
        notifications: [],
    );

    expect($config->shouldBackupDatabase())->toBeTrue();
    expect($config->getDatabaseConnection())->toBe('mysql');
    expect($config->getDatabaseHost())->toBe('db.example.com');
    expect($config->getDatabasePort())->toBe(3306);
    expect($config->getDatabaseName())->toBe('myapp');
    expect($config->getDatabaseUsername())->toBe('dbuser');
    expect($config->getDatabasePassword())->toBe('dbpass');
    expect($config->getKeepBackups())->toBe(10);
});

test('database backup uses default keep backups value', function () {
    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: [],
        hooks: [],
        healthCheck: [],
        sharedPaths: [],
        database: ['backup_enabled' => true],
        notifications: [],
    );

    expect($config->getKeepBackups())->toBe(5);
});

test('database backup skips when disabled', function () {
    $sshMock = SshMockHelper::mockConnection();

    // Should not execute any commands when disabled
    $sshMock->shouldNotReceive('execute');

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new BackupDatabase($executor, $this->logger);

    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: [],
        hooks: [],
        healthCheck: [],
        sharedPaths: [],
        database: ['backup_enabled' => false],
        notifications: [],
    );

    $action->handle($config, $this->release);

    expect(true)->toBeTrue();
});

test('database backup creates MySQL backup successfully', function () {
    $sshMock = SshMockHelper::mockConnection();

    $executedCommands = [];

    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) use (&$executedCommands) {
            $executedCommands[] = $command;

            return new CommandResult(0, '', $command);
        });

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new BackupDatabase($executor, $this->logger);

    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: [],
        hooks: [],
        healthCheck: [],
        sharedPaths: [],
        database: [
            'backup_enabled' => true,
            'connection' => 'mysql',
            'database' => 'testdb',
            'username' => 'testuser',
            'password' => 'testpass',
        ],
        notifications: [],
    );

    $action->handle($config, $this->release);

    // Should create backup directory
    $mkdirCommand = array_filter($executedCommands, fn ($cmd) => str_contains($cmd, 'mkdir -p'));
    expect($mkdirCommand)->not->toBeEmpty();

    // Should execute mysqldump
    $mysqldumpCommand = array_filter($executedCommands, fn ($cmd) => str_contains($cmd, 'mysqldump'));
    expect($mysqldumpCommand)->not->toBeEmpty();

    // Should cleanup old backups
    $cleanupCommand = array_filter($executedCommands, fn ($cmd) => str_contains($cmd, 'ls -t'));
    expect($cleanupCommand)->not->toBeEmpty();
});

test('database backup creates PostgreSQL backup successfully', function () {
    $sshMock = SshMockHelper::mockConnection();

    $executedCommands = [];

    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) use (&$executedCommands) {
            $executedCommands[] = $command;

            return new CommandResult(0, '', $command);
        });

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new BackupDatabase($executor, $this->logger);

    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: [],
        hooks: [],
        healthCheck: [],
        sharedPaths: [],
        database: [
            'backup_enabled' => true,
            'connection' => 'pgsql',
            'database' => 'testdb',
            'username' => 'testuser',
            'password' => 'testpass',
        ],
        notifications: [],
    );

    $action->handle($config, $this->release);

    // Should execute pg_dump
    $pgdumpCommand = array_filter($executedCommands, fn ($cmd) => str_contains($cmd, 'pg_dump'));
    expect($pgdumpCommand)->not->toBeEmpty();
});

test('database backup throws exception for unsupported connection', function () {
    $sshMock = SshMockHelper::mockConnection();

    $sshMock->shouldReceive('execute')
        ->andReturn(new CommandResult(0, '', 'mkdir'));

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new BackupDatabase($executor, $this->logger);

    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: [],
        hooks: [],
        healthCheck: [],
        sharedPaths: [],
        database: [
            'backup_enabled' => true,
            'connection' => 'sqlite',
            'database' => 'testdb',
            'username' => 'testuser',
        ],
        notifications: [],
    );

    expect(fn () => $action->handle($config, $this->release))
        ->toThrow(DatabaseBackupException::class, 'Unsupported database connection');
});

test('database backup throws exception when database name missing', function () {
    $sshMock = SshMockHelper::mockConnection();

    $sshMock->shouldReceive('execute')
        ->andReturn(new CommandResult(0, '', 'mkdir'));

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new BackupDatabase($executor, $this->logger);

    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: [],
        hooks: [],
        healthCheck: [],
        sharedPaths: [],
        database: [
            'backup_enabled' => true,
            'connection' => 'mysql',
            'username' => 'testuser',
        ],
        notifications: [],
    );

    expect(fn () => $action->handle($config, $this->release))
        ->toThrow(DatabaseBackupException::class, 'Database name and username are required');
});

test('database backup throws exception when backup command fails', function () {
    $sshMock = SshMockHelper::mockConnection();

    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) {
            if (str_contains($command, 'mkdir')) {
                return new CommandResult(0, '', $command);
            }

            if (str_contains($command, 'mysqldump')) {
                // Simulate backup failure
                return new CommandResult(1, 'Access denied', $command);
            }

            return new CommandResult(0, '', $command);
        });

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new BackupDatabase($executor, $this->logger);

    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: [],
        hooks: [],
        healthCheck: [],
        sharedPaths: [],
        database: [
            'backup_enabled' => true,
            'connection' => 'mysql',
            'database' => 'testdb',
            'username' => 'testuser',
        ],
        notifications: [],
    );

    expect(fn () => $action->handle($config, $this->release))
        ->toThrow(DatabaseBackupException::class, 'MySQL backup failed');
});
test('database backup throws exception when postgres database name missing', function () {
    $sshMock = SshMockHelper::mockConnection();

    $sshMock->shouldReceive('execute')
        ->andReturn(new CommandResult(0, '', 'mkdir'));

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new BackupDatabase($executor, $this->logger);

    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: [],
        hooks: [],
        healthCheck: [],
        sharedPaths: [],
        database: [
            'backup_enabled' => true,
            'connection' => 'pgsql',
            'username' => 'testuser',
        ],
        notifications: [],
    );

    expect(fn () => $action->handle($config, $this->release))
        ->toThrow(DatabaseBackupException::class, 'Database name and username are required');
});

test('database backup throws exception when postgres backup command fails', function () {
    $sshMock = SshMockHelper::mockConnection();

    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) {
            if (str_contains($command, 'mkdir')) {
                return new CommandResult(0, '', $command);
            }

            if (str_contains($command, 'pg_dump')) {
                // Simulate backup failure
                return new CommandResult(1, 'Access denied', $command);
            }

            return new CommandResult(0, '', $command);
        });

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new BackupDatabase($executor, $this->logger);

    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: [],
        hooks: [],
        healthCheck: [],
        sharedPaths: [],
        database: [
            'backup_enabled' => true,
            'connection' => 'pgsql',
            'database' => 'testdb',
            'username' => 'testuser',
        ],
        notifications: [],
    );

    expect(fn () => $action->handle($config, $this->release))
        ->toThrow(DatabaseBackupException::class, 'PostgreSQL backup failed');
});
