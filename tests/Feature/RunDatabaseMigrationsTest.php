<?php

declare(strict_types=1);

use App\Actions\RunDatabaseMigrations;
use App\Services\RemoteExecutor;
use App\ValueObjects\CommandResult;
use App\ValueObjects\DeploymentConfig;
use App\ValueObjects\Release;
use Psr\Log\NullLogger;
use Tests\Helpers\SshMockHelper;

beforeEach(function () {
    $this->logger = new NullLogger;
    $this->release = new Release(
        name: '20250101-120000',
        path: '/var/www/app/releases/20250101-120000',
        createdAt: new DateTimeImmutable,
    );
});

test('run migrations executes artisan migrate command', function () {
    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: ['run_migrations' => true],
        hooks: [],
        healthCheck: [],
        sharedPaths: [],
        database: [],
        notifications: [],
    );

    $sshMock = SshMockHelper::mockConnection();

    $executedCommands = [];

    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) use (&$executedCommands) {
            $executedCommands[] = $command;

            return new CommandResult(0, 'Migrating...', $command);
        });

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new RunDatabaseMigrations($executor, $this->logger);

    $action->handle($config, $this->release);

    $migrateCommands = array_filter($executedCommands, fn ($cmd) => str_contains($cmd, 'artisan migrate'));

    expect($migrateCommands)->not->toBeEmpty();
});

test('run migrations uses force flag in production', function () {
    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: ['run_migrations' => true],
        hooks: [],
        healthCheck: [],
        sharedPaths: [],
        database: [],
        notifications: [],
    );

    $sshMock = SshMockHelper::mockConnection();

    $executedCommands = [];

    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) use (&$executedCommands) {
            $executedCommands[] = $command;

            return new CommandResult(0, 'Migrating...', $command);
        });

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new RunDatabaseMigrations($executor, $this->logger);

    $action->handle($config, $this->release);

    $migrateCommand = array_filter($executedCommands, fn ($cmd) => str_contains($cmd, 'artisan migrate'));

    expect($migrateCommand)->not->toBeEmpty();
    expect(reset($migrateCommand))->toContain('--force');
});

test('run migrations executes in release directory', function () {
    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: ['run_migrations' => true],
        hooks: [],
        healthCheck: [],
        sharedPaths: [],
        database: [],
        notifications: [],
    );

    $sshMock = SshMockHelper::mockConnection();

    $executedCommands = [];

    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) use (&$executedCommands) {
            $executedCommands[] = $command;

            return new CommandResult(0, 'Migrating...', $command);
        });

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new RunDatabaseMigrations($executor, $this->logger);

    $action->handle($config, $this->release);

    $migrateCommand = array_filter($executedCommands, fn ($cmd) => str_contains($cmd, 'artisan migrate'));

    expect($migrateCommand)->not->toBeEmpty();
    expect(reset($migrateCommand))->toContain('20250101-120000');
});

test('run migrations skips when disabled', function () {
    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: ['run_migrations' => false],
        hooks: [],
        healthCheck: [],
        sharedPaths: [],
        database: [],
        notifications: [],
    );

    $sshMock = SshMockHelper::mockConnection();

    $sshMock->shouldReceive('execute')->never();

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new RunDatabaseMigrations($executor, $this->logger);

    $action->handle($config, $this->release);

    expect(true)->toBeTrue();
});
