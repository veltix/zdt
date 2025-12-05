<?php

declare(strict_types=1);

use App\Actions\SyncEnvironmentFile;
use App\Services\FileSync;
use App\Services\RemoteExecutor;
use App\ValueObjects\CommandResult;
use App\ValueObjects\DeploymentConfig;
use App\ValueObjects\Release;
use Psr\Log\NullLogger;
use Tests\Helpers\SshMockHelper;

beforeEach(function () {
    $this->logger = new NullLogger;
    $this->config = new DeploymentConfig(
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

    $this->release = new Release(
        name: '20250101-120000',
        path: '/var/www/app/releases/20250101-120000',
        createdAt: new DateTimeImmutable,
    );
});

test('sync environment file copies env from shared', function () {
    $sshMock = SshMockHelper::mockConnection();

    $executedCommands = [];

    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) use (&$executedCommands) {
            $executedCommands[] = $command;

            if (str_contains($command, 'test -f')) {
                // .env exists in shared
                return new CommandResult(0, '', $command);
            }

            return new CommandResult(0, '', $command);
        });

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $fileSync = new FileSync($sshMock, $executor, $this->logger);
    $action = new SyncEnvironmentFile($fileSync, $executor, $this->logger);

    $action->handle($this->config, $this->release);

    $cpCommands = array_filter($executedCommands, fn ($cmd) => str_contains($cmd, 'cp'));

    expect($cpCommands)->not->toBeEmpty();
});

test('sync environment file copies from shared/.env to release', function () {
    $sshMock = SshMockHelper::mockConnection();

    $executedCommands = [];

    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) use (&$executedCommands) {
            $executedCommands[] = $command;

            if (str_contains($command, 'test -f')) {
                return new CommandResult(0, '', $command);
            }

            return new CommandResult(0, '', $command);
        });

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $fileSync = new FileSync($sshMock, $executor, $this->logger);
    $action = new SyncEnvironmentFile($fileSync, $executor, $this->logger);

    $action->handle($this->config, $this->release);

    $cpCommand = array_filter($executedCommands, fn ($cmd) => str_contains($cmd, 'cp'));

    expect($cpCommand)->not->toBeEmpty();
    expect(reset($cpCommand))->toContain('shared/.env');
});

test('sync environment file creates env in release directory', function () {
    $sshMock = SshMockHelper::mockConnection();

    $executedCommands = [];

    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) use (&$executedCommands) {
            $executedCommands[] = $command;

            if (str_contains($command, 'test -f')) {
                return new CommandResult(0, '', $command);
            }

            return new CommandResult(0, '', $command);
        });

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $fileSync = new FileSync($sshMock, $executor, $this->logger);
    $action = new SyncEnvironmentFile($fileSync, $executor, $this->logger);

    $action->handle($this->config, $this->release);

    $cpCommand = array_filter($executedCommands, fn ($cmd) => str_contains($cmd, 'cp'));

    expect($cpCommand)->not->toBeEmpty();
    expect(reset($cpCommand))->toContain('20250101-120000/.env');
});
