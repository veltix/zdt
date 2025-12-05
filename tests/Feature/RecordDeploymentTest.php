<?php

declare(strict_types=1);

use App\Actions\RecordDeployment;
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
});

test('record deployment writes log entry', function () {
    $sshMock = SshMockHelper::mockConnection();

    $executedCommands = [];

    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) use (&$executedCommands) {
            $executedCommands[] = $command;

            return new CommandResult(0, '', $command);
        });

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new RecordDeployment($executor, $this->logger);

    $release = new Release(
        name: '20250101-120000',
        path: '/var/www/app/releases/20250101-120000',
        createdAt: new DateTimeImmutable,
        commitHash: 'abc123def',
        branch: 'main',
    );

    $action->handle($this->config, $release);

    // Should execute echo command to append to log
    $echoCommands = array_filter($executedCommands, fn ($cmd) => str_contains($cmd, 'echo'));

    expect($echoCommands)->not->toBeEmpty();
});

test('record deployment writes to correct log file', function () {
    $sshMock = SshMockHelper::mockConnection();

    $executedCommands = [];

    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) use (&$executedCommands) {
            $executedCommands[] = $command;

            return new CommandResult(0, '', $command);
        });

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new RecordDeployment($executor, $this->logger);

    $release = new Release(
        name: '20250101-120000',
        path: '/var/www/app/releases/20250101-120000',
        createdAt: new DateTimeImmutable,
    );

    $action->handle($this->config, $release);

    // Should write to .zdt/deployment.log
    $logCommand = array_filter($executedCommands, fn ($cmd) => str_contains($cmd, '.zdt/deployment.log'));

    expect($logCommand)->not->toBeEmpty();
});

test('record deployment includes release information', function () {
    $sshMock = SshMockHelper::mockConnection();

    $executedCommands = [];

    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) use (&$executedCommands) {
            $executedCommands[] = $command;

            return new CommandResult(0, '', $command);
        });

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new RecordDeployment($executor, $this->logger);

    $release = new Release(
        name: '20250101-120000',
        path: '/var/www/app/releases/20250101-120000',
        createdAt: new DateTimeImmutable,
        commitHash: 'abc123',
        branch: 'production',
    );

    $action->handle($this->config, $release);

    $command = reset($executedCommands);

    // Command should contain release name
    expect($command)->toContain('20250101-120000');

    // Command should contain commit hash
    expect($command)->toContain('abc123');

    // Command should contain branch
    expect($command)->toContain('production');
});

test('record deployment uses append operator', function () {
    $sshMock = SshMockHelper::mockConnection();

    $executedCommands = [];

    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) use (&$executedCommands) {
            $executedCommands[] = $command;

            return new CommandResult(0, '', $command);
        });

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new RecordDeployment($executor, $this->logger);

    $release = new Release(
        name: '20250101-120000',
        path: '/var/www/app/releases/20250101-120000',
        createdAt: new DateTimeImmutable,
    );

    $action->handle($this->config, $release);

    $command = reset($executedCommands);

    // Should use >> to append, not > to overwrite
    expect($command)->toContain('>>');
});
