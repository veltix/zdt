<?php

declare(strict_types=1);

use App\Actions\CompileAssets;
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

test('compile assets skips when npm disabled', function () {
    $sshMock = SshMockHelper::mockConnection();

    // Should not execute any commands when npm is disabled
    $sshMock->shouldNotReceive('execute');

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new CompileAssets($executor, $this->logger);

    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: ['use_npm' => false],
        hooks: [],
        healthCheck: [],
        sharedPaths: [],
        database: [],
        notifications: [],
    );

    $action->handle($config, $this->release);

    expect(true)->toBeTrue();
});

test('compile assets runs npm ci when enabled', function () {
    $sshMock = SshMockHelper::mockConnection();

    $executedCommands = [];

    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) use (&$executedCommands) {
            $executedCommands[] = $command;

            return new CommandResult(0, 'Installing...', $command);
        });

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new CompileAssets($executor, $this->logger);

    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: ['use_npm' => true],
        hooks: [],
        healthCheck: [],
        sharedPaths: [],
        database: [],
        notifications: [],
    );

    $action->handle($config, $this->release);

    // Should execute npm ci
    $npmCiCommand = array_filter($executedCommands, fn ($cmd) => str_contains($cmd, 'npm c'));

    expect($npmCiCommand)->not->toBeEmpty();
});

test('compile assets runs npm run build', function () {
    $sshMock = SshMockHelper::mockConnection();

    $executedCommands = [];

    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) use (&$executedCommands) {
            $executedCommands[] = $command;

            return new CommandResult(0, 'Building...', $command);
        });

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new CompileAssets($executor, $this->logger);

    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: ['use_npm' => true],
        hooks: [],
        healthCheck: [],
        sharedPaths: [],
        database: [],
        notifications: [],
    );

    $action->handle($config, $this->release);

    // Should execute npm run build
    $npmBuildCommand = array_filter($executedCommands, fn ($cmd) => str_contains($cmd, 'npm run build'));

    expect($npmBuildCommand)->not->toBeEmpty();
});

test('compile assets executes in release directory', function () {
    $sshMock = SshMockHelper::mockConnection();

    $executedCommands = [];

    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) use (&$executedCommands) {
            $executedCommands[] = $command;

            return new CommandResult(0, 'Success', $command);
        });

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new CompileAssets($executor, $this->logger);

    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: ['use_npm' => true],
        hooks: [],
        healthCheck: [],
        sharedPaths: [],
        database: [],
        notifications: [],
    );

    $action->handle($config, $this->release);

    // Commands should include cd to release directory
    $cdCommands = array_filter($executedCommands, fn ($cmd) => str_contains($cmd, 'cd /var/www/app/releases/20250101-120000'));

    expect($cdCommands)->not->toBeEmpty();
});
