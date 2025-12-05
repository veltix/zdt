<?php

declare(strict_types=1);

use App\Actions\CheckoutBranch;
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

test('checkout branch executes git checkout command', function () {
    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'develop'],
        paths: ['deploy_to' => '/var/www/app'],
        options: [],
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

            return new CommandResult(0, '', $command);
        });

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new CheckoutBranch($executor, $this->logger);

    $action->handle($config, $this->release);

    $gitCheckoutCommands = array_filter($executedCommands, fn ($cmd) => str_contains($cmd, 'git checkout'));

    expect($gitCheckoutCommands)->not->toBeEmpty();
});

test('checkout branch uses correct branch name', function () {
    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'staging'],
        paths: ['deploy_to' => '/var/www/app'],
        options: [],
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

            return new CommandResult(0, '', $command);
        });

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new CheckoutBranch($executor, $this->logger);

    $action->handle($config, $this->release);

    $gitCheckoutCommand = array_filter($executedCommands, fn ($cmd) => str_contains($cmd, 'git checkout'));

    expect($gitCheckoutCommand)->not->toBeEmpty();
    expect(reset($gitCheckoutCommand))->toContain('staging');
});

test('checkout branch defaults to main when not specified', function () {
    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git'],
        paths: ['deploy_to' => '/var/www/app'],
        options: [],
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

            return new CommandResult(0, '', $command);
        });

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new CheckoutBranch($executor, $this->logger);

    $action->handle($config, $this->release);

    $gitCheckoutCommand = array_filter($executedCommands, fn ($cmd) => str_contains($cmd, 'git checkout'));

    expect($gitCheckoutCommand)->not->toBeEmpty();
    expect(reset($gitCheckoutCommand))->toContain('main');
});
