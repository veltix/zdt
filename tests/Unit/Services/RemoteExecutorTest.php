<?php

declare(strict_types=1);

use App\Exceptions\RemoteExecutionException;
use App\Services\RemoteExecutor;
use App\ValueObjects\CommandResult;
use Tests\Helpers\FakeLogger;
use Tests\Helpers\FakeSshConnection;

beforeEach(function () {
    $this->ssh = new FakeSshConnection();
    $this->ssh->connect();
    $this->logger = new FakeLogger();
    $this->executor = new RemoteExecutor($this->ssh, $this->logger);
});

test('execute runs command successfully', function () {
    $this->ssh->setCommandResult(
        'echo "hello"',
        new CommandResult(0, 'hello', 'echo "hello"')
    );

    $result = $this->executor->execute('echo "hello"');

    expect($result->exitCode)->toBe(0)
        ->and($result->output)->toBe('hello')
        ->and($result->isSuccessful())->toBeTrue()
        ->and($this->logger->hasLog('debug', 'Executing: echo "hello"'))->toBeTrue();
});

test('execute throws exception on failure when throwOnError is true', function () {
    $this->ssh->setCommandResult(
        'fail',
        new CommandResult(1, 'error output', 'fail')
    );

    expect(fn () => $this->executor->execute('fail'))
        ->toThrow(RemoteExecutionException::class, 'Command failed with exit code 1');
});

test('execute returns failed result when throwOnError is false', function () {
    $this->ssh->setCommandResult(
        'fail',
        new CommandResult(1, 'error output', 'fail')
    );

    $result = $this->executor->execute('fail', throwOnError: false);

    expect($result->exitCode)->toBe(1)
        ->and($result->output)->toBe('error output')
        ->and($result->isFailed())->toBeTrue();
});

test('executeWithRetry succeeds on first attempt', function () {
    $this->ssh->setCommandResult(
        'success',
        new CommandResult(0, 'ok', 'success')
    );

    $result = $this->executor->executeWithRetry('success');

    expect($result->exitCode)->toBe(0)
        ->and($result->isSuccessful())->toBeTrue()
        ->and($this->ssh->executedCommands)->toHaveCount(1);
});

test('executeWithRetry retries on failure and succeeds', function () {
    $attempts = 0;
    $this->ssh->commandResults['retry'] = function () use (&$attempts) {
        $attempts++;
        if ($attempts < 3) {
            return new CommandResult(1, 'temporary error', 'retry');
        }

        return new CommandResult(0, 'success', 'retry');
    };

    $result = $this->executor->executeWithRetry('retry', maxAttempts: 3, delaySeconds: 0);

    expect($result->isSuccessful())->toBeTrue()
        ->and($attempts)->toBe(3)
        ->and($this->ssh->executedCommands)->toHaveCount(3);
});

test('executeWithRetry throws after max attempts', function () {
    $this->ssh->setCommandResult(
        'always-fail',
        new CommandResult(1, 'persistent error', 'always-fail')
    );

    expect(fn () => $this->executor->executeWithRetry('always-fail', maxAttempts: 2, delaySeconds: 0))
        ->toThrow(RemoteExecutionException::class);

    expect($this->ssh->executedCommands)->toHaveCount(2);
});

test('executeWithRetry returns failed result when throwOnError is false', function () {
    $this->ssh->setCommandResult(
        'fail',
        new CommandResult(1, 'error', 'fail')
    );

    $result = $this->executor->executeWithRetry('fail', maxAttempts: 1, delaySeconds: 0, throwOnError: false);

    expect($result->isFailed())->toBeTrue();
});

test('executeMultiple runs all commands', function () {
    $commands = [
        'command1',
        'command2',
        'command3',
    ];

    $this->executor->executeMultiple($commands, throwOnError: false);

    expect($this->ssh->executedCommands)->toHaveCount(3)
        ->and($this->ssh->executedCommands)->toContain('command1')
        ->and($this->ssh->executedCommands)->toContain('command2')
        ->and($this->ssh->executedCommands)->toContain('command3');
});

test('executeMultiple stops on failure when throwOnError is true', function () {
    $this->ssh->setCommandResult(
        'fail',
        new CommandResult(1, 'error', 'fail')
    );

    expect(fn () => $this->executor->executeMultiple(['success', 'fail', 'never-runs']))
        ->toThrow(RemoteExecutionException::class);

    expect($this->ssh->executedCommands)->toHaveCount(2);
});

test('executeInDirectory changes to directory before running command', function () {
    $result = $this->executor->executeInDirectory('/var/www', 'ls -la');

    expect($this->ssh->executedCommands)->toContain('cd /var/www && ls -la')
        ->and($result->isSuccessful())->toBeTrue();
});

test('executeInDirectory throws on failure when throwOnError is true', function () {
    $this->ssh->setCommandResult(
        'cd /var/www && fail',
        new CommandResult(1, 'error', 'cd /var/www && fail')
    );

    expect(fn () => $this->executor->executeInDirectory('/var/www', 'fail'))
        ->toThrow(RemoteExecutionException::class);
});
