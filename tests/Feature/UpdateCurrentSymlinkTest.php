<?php

declare(strict_types=1);

use App\Actions\UpdateCurrentSymlink;
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

test('update symlink creates atomic symlink switch', function () {
    $sshMock = SshMockHelper::mockConnection();

    $executedCommands = [];

    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) use (&$executedCommands) {
            $executedCommands[] = $command;

            return new CommandResult(0, '', $command);
        });

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $fileSync = new FileSync($sshMock, $executor, $this->logger);
    $action = new UpdateCurrentSymlink($fileSync, $this->logger);

    $action->handle($this->config, $this->release);

    // Should create symlink command (ln -nfs for atomic operation)
    $symlinkCommands = array_filter($executedCommands, fn ($cmd) => str_contains($cmd, 'ln -nfs'));

    expect($symlinkCommands)->not->toBeEmpty();
});

test('update symlink points to correct release', function () {
    $sshMock = SshMockHelper::mockConnection();

    $executedCommands = [];

    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) use (&$executedCommands) {
            $executedCommands[] = $command;

            return new CommandResult(0, '', $command);
        });

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $fileSync = new FileSync($sshMock, $executor, $this->logger);
    $action = new UpdateCurrentSymlink($fileSync, $this->logger);

    $action->handle($this->config, $this->release);

    $symlinkCommand = array_filter($executedCommands, fn ($cmd) => str_contains($cmd, 'ln -nfs'));

    expect($symlinkCommand)->not->toBeEmpty();
    expect(reset($symlinkCommand))->toContain('20250101-120000');
});

test('update symlink creates current link in deploy path', function () {
    $sshMock = SshMockHelper::mockConnection();

    $executedCommands = [];

    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) use (&$executedCommands) {
            $executedCommands[] = $command;

            return new CommandResult(0, '', $command);
        });

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $fileSync = new FileSync($sshMock, $executor, $this->logger);
    $action = new UpdateCurrentSymlink($fileSync, $this->logger);

    $action->handle($this->config, $this->release);

    $symlinkCommand = array_filter($executedCommands, fn ($cmd) => str_contains($cmd, 'ln -nfs'));

    expect($symlinkCommand)->not->toBeEmpty();
    expect(reset($symlinkCommand))->toContain('/var/www/app/current');
});
