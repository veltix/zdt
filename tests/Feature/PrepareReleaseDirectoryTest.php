<?php

declare(strict_types=1);

use App\Actions\PrepareReleaseDirectory;
use App\Services\RemoteExecutor;
use App\ValueObjects\CommandResult;
use App\ValueObjects\DeploymentConfig;
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

test('prepare release creates correct directory structure', function () {
    $sshMock = SshMockHelper::mockConnection();

    $sshMock->shouldReceive('execute')
        ->andReturn(new CommandResult(0, '', 'mkdir'));

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new PrepareReleaseDirectory($executor, $this->logger);

    $release = $action->handle($this->config);

    expect($release)->not->toBeNull();
    expect($release->name)->toMatch('/^\d{14}$/');
    expect($release->path)->toContain('/var/www/app/releases/');
});

test('prepare release directory name format is correct', function () {
    $sshMock = SshMockHelper::mockConnection();

    $sshMock->shouldReceive('execute')
        ->andReturn(new CommandResult(0, '', 'mkdir'));

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new PrepareReleaseDirectory($executor, $this->logger);

    $release = $action->handle($this->config);

    // Check timestamp format: YYYYMMDDHHmmss (14 digits)
    expect($release->name)->toMatch('/^\d{14}$/');
});

test('prepare release creates shared directory if missing', function () {
    $sshMock = SshMockHelper::mockConnection();

    $executedCommands = [];

    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) use (&$executedCommands) {
            $executedCommands[] = $command;

            return new CommandResult(0, '', $command);
        });

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new PrepareReleaseDirectory($executor, $this->logger);

    $release = $action->handle($this->config);

    $mkdirShared = array_filter($executedCommands, fn ($cmd) => str_contains($cmd, 'shared'));

    expect($mkdirShared)->not->toBeEmpty();
    expect($release)->not->toBeNull();
});
