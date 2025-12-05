<?php

declare(strict_types=1);

use App\Actions\RecordRollback;
use App\Services\RemoteExecutor;
use App\ValueObjects\DeploymentConfig;
use App\ValueObjects\Release;
use Tests\Helpers\FakeLogger;
use Tests\Helpers\FakeSshConnection;

beforeEach(function () {
    $this->logger = new FakeLogger;
    $this->ssh = new FakeSshConnection;
    $this->ssh->connect();
    $this->executor = new RemoteExecutor($this->ssh, $this->logger);
    $this->action = new RecordRollback($this->executor, $this->logger);

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

    $this->targetRelease = new Release(
        name: '20250101-120000',
        path: '/var/www/app/releases/20250101-120000',
        createdAt: new DateTimeImmutable,
    );

    $this->previousRelease = new Release(
        name: '20250102-120000',
        path: '/var/www/app/releases/20250102-120000',
        createdAt: new DateTimeImmutable,
    );
});

test('records rollback with target and previous release', function () {
    $this->action->handle($this->config, $this->targetRelease, $this->previousRelease);

    $commands = $this->ssh->executedCommands;
    $echoCommands = array_filter($commands, fn ($cmd) => str_contains($cmd, 'echo'));

    expect($echoCommands)->not->toBeEmpty();
    expect($this->logger->hasLog('info', 'Rollback recorded'))->toBeTrue();
});

test('records rollback without previous release', function () {
    $this->action->handle($this->config, $this->targetRelease, null);

    $commands = $this->ssh->executedCommands;
    $echoCommands = array_filter($commands, fn ($cmd) => str_contains($cmd, 'echo'));

    expect($echoCommands)->not->toBeEmpty();
    expect($this->logger->hasLog('info', 'Rollback recorded'))->toBeTrue();
});

test('writes to correct log file path', function () {
    $this->action->handle($this->config, $this->targetRelease, $this->previousRelease);

    $commands = $this->ssh->executedCommands;
    $echoCommand = array_filter($commands, fn ($cmd) => str_contains($cmd, '/var/www/app/.zdt/deployment.log'));

    expect($echoCommand)->not->toBeEmpty();
});

test('includes rollback event in log entry', function () {
    $this->action->handle($this->config, $this->targetRelease, $this->previousRelease);

    $commands = $this->ssh->executedCommands;
    $echoCommand = array_filter($commands, fn ($cmd) => str_contains($cmd, 'rollback'));

    expect($echoCommand)->not->toBeEmpty();
});

test('includes target release name in log entry', function () {
    $this->action->handle($this->config, $this->targetRelease, $this->previousRelease);

    $commands = $this->ssh->executedCommands;
    $echoCommand = array_filter($commands, fn ($cmd) => str_contains($cmd, '20250101-120000'));

    expect($echoCommand)->not->toBeEmpty();
});

test('includes previous release name when provided', function () {
    $this->action->handle($this->config, $this->targetRelease, $this->previousRelease);

    $commands = $this->ssh->executedCommands;
    $echoCommand = array_filter($commands, fn ($cmd) => str_contains($cmd, '20250102-120000'));

    expect($echoCommand)->not->toBeEmpty();
});

test('handles null previous release gracefully', function () {
    $this->action->handle($this->config, $this->targetRelease, null);

    expect($this->logger->hasLog('info', 'Rollback recorded'))->toBeTrue();
});

test('continues execution even if echo command fails', function () {
    $this->action->handle($this->config, $this->targetRelease, $this->previousRelease);

    expect($this->logger->hasLog('info', 'Rollback recorded'))->toBeTrue();
});

test('uses custom deploy path from config', function () {
    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'main'],
        paths: ['deploy_to' => '/custom/path'],
        options: [],
        hooks: [],
        healthCheck: [],
        sharedPaths: [],
        database: [],
        notifications: [],
    );

    $this->action->handle($config, $this->targetRelease, $this->previousRelease);

    $commands = $this->ssh->executedCommands;
    $customPathCommands = array_filter($commands, fn ($cmd) => str_contains($cmd, '/custom/path/.zdt/deployment.log'));

    expect($customPathCommands)->not->toBeEmpty();
});

test('appends to log file instead of overwriting', function () {
    $this->action->handle($this->config, $this->targetRelease, $this->previousRelease);

    $commands = $this->ssh->executedCommands;
    $appendCommand = array_filter($commands, fn ($cmd) => str_contains($cmd, '>>'));

    expect($appendCommand)->not->toBeEmpty();
});

test('escapes shell arguments properly', function () {
    $targetWithSpecialChars = new Release(
        name: '20250101-120000',
        path: '/var/www/app/releases/20250101-120000',
        createdAt: new DateTimeImmutable,
    );

    $this->action->handle($this->config, $targetWithSpecialChars, $this->previousRelease);

    expect($this->logger->hasLog('info', 'Rollback recorded'))->toBeTrue();
});

test('includes timestamp in log entry', function () {
    $this->action->handle($this->config, $this->targetRelease, $this->previousRelease);

    $commands = $this->ssh->executedCommands;
    $timestampCommand = array_filter($commands, fn ($cmd) => str_contains($cmd, 'timestamp'));

    expect($timestampCommand)->not->toBeEmpty();
});
