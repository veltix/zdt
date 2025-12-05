<?php

declare(strict_types=1);

use App\Actions\CloneRepository;
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

test('clone repository executes git clone command', function () {
    $sshMock = SshMockHelper::mockConnection();

    $executedCommands = [];

    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) use (&$executedCommands) {
            $executedCommands[] = $command;

            return new CommandResult(0, 'Cloning into...', $command);
        });

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new CloneRepository($executor, $this->logger);

    $action->handle($this->config, $this->release);

    $gitCloneCommands = array_filter($executedCommands, fn ($cmd) => str_contains($cmd, 'git clone'));

    expect($gitCloneCommands)->not->toBeEmpty();
});

test('clone repository uses correct repository url', function () {
    $sshMock = SshMockHelper::mockConnection();

    $executedCommands = [];

    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) use (&$executedCommands) {
            $executedCommands[] = $command;

            return new CommandResult(0, 'Cloning into...', $command);
        });

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new CloneRepository($executor, $this->logger);

    $action->handle($this->config, $this->release);

    $gitCloneCommand = array_filter($executedCommands, fn ($cmd) => str_contains($cmd, 'git clone'));

    expect($gitCloneCommand)->not->toBeEmpty();
    expect(reset($gitCloneCommand))->toContain('https://github.com/test/repo.git');
});

test('clone repository clones into release path', function () {
    $sshMock = SshMockHelper::mockConnection();

    $executedCommands = [];

    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) use (&$executedCommands) {
            $executedCommands[] = $command;

            return new CommandResult(0, 'Cloning into...', $command);
        });

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new CloneRepository($executor, $this->logger);

    $action->handle($this->config, $this->release);

    $gitCloneCommand = array_filter($executedCommands, fn ($cmd) => str_contains($cmd, 'git clone'));

    expect($gitCloneCommand)->not->toBeEmpty();
    expect(reset($gitCloneCommand))->toContain('/var/www/app/releases/20250101-120000');
});
test('clone repository executes before and after hooks', function () {
    $sshMock = SshMockHelper::mockConnection();

    $executedCommands = [];

    // Mock execute (for clone and keyscan)
    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) use (&$executedCommands) {
            $executedCommands[] = "EXEC: {$command}";

            return new CommandResult(0, '', $command);
        });

    // Mock executeInDirectory (for hooks)
    // Note: RemoteExecutor implementation of executeInDirectory might just call execute with cd?
    // Let's check RemoteExecutor source if needed, or assume it calls execute?
    // FakeSshConnection mocks `execute`. Action uses `RemoteExecutor`.
    // RemoteExecutor `executeInDirectory` usually does `cd $path && $command`.
    // But `CloneRepository` handles hooks via `executor->executeInDirectory`.
    // We should mock `execute` on the SSH connection because `RemoteExecutor` delegates to it.
    // However, does `RemoteExecutor` call `execute` on SSH mock? Yes.

    // We can rely on `execute` capturing the full command string.

    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: [],
        hooks: [
            'before_clone' => ['echo "before"'],
            'after_clone' => ['echo "after"'],
        ],
        healthCheck: [],
        sharedPaths: [],
        database: [],
        notifications: [],
    );

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new CloneRepository($executor, $this->logger);

    $action->handle($config, $this->release);

    $executionString = implode("\n", $executedCommands);

    // Check for hook commands
    // `executeInDirectory` usually transforms `cmd` to `cd path && cmd`.
    // We verify partial match.
    // Wait, regex might be tricky if formatting changes.
    // Let's inspect what is actually sent.

    $beforeHookFound = count(array_filter($executedCommands, fn ($c) => str_contains($c, 'echo "before"'))) > 0;
    $afterHookFound = count(array_filter($executedCommands, fn ($c) => str_contains($c, 'echo "after"'))) > 0;

    expect($beforeHookFound)->toBeTrue('Before hook not executed');
    expect($afterHookFound)->toBeTrue('After hook not executed');
});
