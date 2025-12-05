<?php

declare(strict_types=1);

use App\Actions\OptimizeApplication;
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

test('optimize application executes before_activate hooks', function () {
    $sshMock = SshMockHelper::mockConnection();

    $executedCommands = [];

    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) use (&$executedCommands) {
            $executedCommands[] = $command;

            return new CommandResult(0, '', $command);
        });

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new OptimizeApplication($executor, $this->logger);

    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: [],
        hooks: [
            'before_activate' => [
                'php artisan config:cache',
                'php artisan route:cache',
            ],
        ],
        healthCheck: [],
        sharedPaths: [],
        database: [],
        notifications: [],
    );

    $action->handle($config, $this->release);

    // Should execute config:cache
    $configCacheCommand = array_filter($executedCommands, fn ($cmd) => str_contains($cmd, 'config:cache'));
    expect($configCacheCommand)->not->toBeEmpty();

    // Should execute route:cache
    $routeCacheCommand = array_filter($executedCommands, fn ($cmd) => str_contains($cmd, 'route:cache'));
    expect($routeCacheCommand)->not->toBeEmpty();
});

test('optimize application does nothing when no hooks configured', function () {
    $sshMock = SshMockHelper::mockConnection();

    // Should not execute any commands when no hooks
    $sshMock->shouldNotReceive('execute');

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new OptimizeApplication($executor, $this->logger);

    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: [],
        hooks: [],
        healthCheck: [],
        sharedPaths: [],
        database: [],
        notifications: [],
    );

    $action->handle($config, $this->release);

    expect(true)->toBeTrue();
});

test('optimize application executes hooks in release directory', function () {
    $sshMock = SshMockHelper::mockConnection();

    $executedCommands = [];

    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) use (&$executedCommands) {
            $executedCommands[] = $command;

            return new CommandResult(0, '', $command);
        });

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new OptimizeApplication($executor, $this->logger);

    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: [],
        hooks: [
            'before_activate' => ['php artisan optimize'],
        ],
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
