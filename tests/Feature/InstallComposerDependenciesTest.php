<?php

declare(strict_types=1);

use App\Actions\InstallComposerDependencies;
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

test('install composer dependencies executes composer install', function () {
    $sshMock = SshMockHelper::mockConnection();

    $executedCommands = [];

    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) use (&$executedCommands) {
            $executedCommands[] = $command;

            return new CommandResult(0, 'Installing dependencies', $command);
        });

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new InstallComposerDependencies($executor, $this->logger);

    $action->handle($this->config, $this->release);

    $composerCommands = array_filter($executedCommands, fn ($cmd) => str_contains($cmd, 'composer install'));

    expect($composerCommands)->not->toBeEmpty();
});

test('install composer dependencies uses production flags', function () {
    $sshMock = SshMockHelper::mockConnection();

    $executedCommands = [];

    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) use (&$executedCommands) {
            $executedCommands[] = $command;

            return new CommandResult(0, 'Installing dependencies', $command);
        });

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new InstallComposerDependencies($executor, $this->logger);

    $action->handle($this->config, $this->release);

    $composerCommand = array_filter($executedCommands, fn ($cmd) => str_contains($cmd, 'composer install'));

    expect($composerCommand)->not->toBeEmpty();
    expect(reset($composerCommand))->toContain('--no-dev');
});

test('install composer dependencies runs in release directory', function () {
    $sshMock = SshMockHelper::mockConnection();

    $executedCommands = [];

    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) use (&$executedCommands) {
            $executedCommands[] = $command;

            return new CommandResult(0, 'Installing dependencies', $command);
        });

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new InstallComposerDependencies($executor, $this->logger);

    $action->handle($this->config, $this->release);

    $composerCommand = array_filter($executedCommands, fn ($cmd) => str_contains($cmd, 'composer install'));

    expect($composerCommand)->not->toBeEmpty();
    expect(reset($composerCommand))->toContain('20250101-120000');
});

test('install composer dependencies skips when disabled', function () {
    $sshMock = SshMockHelper::mockConnection();

    // Should NOT receive composer command
    $sshMock->shouldReceive('execute')->never();

    // If other commands were executed (none expected for this action), we'd use assertion on commands.
    // This action only does composer install. So 'never' is safe?
    // Wait, RemoteExecutor might do 'mkdir'? No, it's done in PrepareReleaseDirectory.
    // InstallComposerDependencies only runs composer.

    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: ['use_composer' => false],
        hooks: [],
        healthCheck: [],
        sharedPaths: [],
        database: [],
        notifications: [],
    );

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new InstallComposerDependencies($executor, $this->logger);

    $action->handle($config, $this->release);

    expect(true)->toBeTrue(); // Implicitly passed by 'never' expectation on mock
});
