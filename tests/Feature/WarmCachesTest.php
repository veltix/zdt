<?php

declare(strict_types=1);

use App\Actions\WarmCaches;
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

test('warm caches executes after_activate hooks', function () {
    $sshMock = SshMockHelper::mockConnection();

    $executedCommands = [];

    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) use (&$executedCommands) {
            $executedCommands[] = $command;

            return new CommandResult(0, '', $command);
        });

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new WarmCaches($executor, $this->logger);

    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: [],
        hooks: [
            'after_activate' => [
                'php artisan queue:restart',
                'php artisan cache:warm',
            ],
        ],
        healthCheck: [],
        sharedPaths: [],
        database: [],
        notifications: [],
    );

    $action->handle($config, $this->release);

    // Should execute queue:restart
    $queueRestartCommand = array_filter($executedCommands, fn ($cmd) => str_contains($cmd, 'queue:restart'));
    expect($queueRestartCommand)->not->toBeEmpty();

    // Should execute cache:warm
    $cacheWarmCommand = array_filter($executedCommands, fn ($cmd) => str_contains($cmd, 'cache:warm'));
    expect($cacheWarmCommand)->not->toBeEmpty();
});

test('warm caches does nothing when no hooks configured', function () {
    $sshMock = SshMockHelper::mockConnection();

    // Should not execute any commands when no hooks
    $sshMock->shouldNotReceive('execute');

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new WarmCaches($executor, $this->logger);

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

test('warm caches executes hooks in release directory', function () {
    $sshMock = SshMockHelper::mockConnection();

    $executedCommands = [];

    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) use (&$executedCommands) {
            $executedCommands[] = $command;

            return new CommandResult(0, '', $command);
        });

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new WarmCaches($executor, $this->logger);

    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: [],
        hooks: [
            'after_activate' => ['php artisan optimize'],
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
