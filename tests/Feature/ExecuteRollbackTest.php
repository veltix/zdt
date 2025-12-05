<?php

declare(strict_types=1);

use App\Actions\ExecuteRollback;
use App\Services\FileSync;
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
    $this->fileSync = new FileSync($this->ssh, $this->executor, $this->logger);
    $this->action = new ExecuteRollback($this->fileSync, $this->executor, $this->logger);

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

test('executes rollback successfully', function () {
    $this->action->handle($this->config, $this->release);

    expect($this->logger->hasLog('info', 'Rolling back to release: 20250101-120000'))->toBeTrue();
    expect($this->logger->hasLog('info', 'Rollback completed successfully'))->toBeTrue();
});

test('creates symlink to target release', function () {
    $this->action->handle($this->config, $this->release);

    $commands = $this->ssh->executedCommands;
    $symlinkCommands = array_filter($commands, fn ($cmd) => str_contains($cmd, 'ln -nfs'));

    expect($symlinkCommands)->not->toBeEmpty();
});

test('uses atomic symlink creation', function () {
    $this->action->handle($this->config, $this->release);

    $commands = $this->ssh->executedCommands;
    $tempLinkCommands = array_filter($commands, fn ($cmd) => str_contains($cmd, 'current.tmp'));
    $mvCommands = array_filter($commands, fn ($cmd) => str_contains($cmd, 'mv -Tf'));

    expect($tempLinkCommands)->not->toBeEmpty();
    expect($mvCommands)->not->toBeEmpty();
});

test('executes after_rollback hooks', function () {
    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: [],
        hooks: ['after_rollback' => ['php artisan cache:clear', 'php artisan config:cache']],
        healthCheck: [],
        sharedPaths: [],
        database: [],
        notifications: [],
    );

    $this->action->handle($config, $this->release);

    $commands = $this->ssh->executedCommands;
    $hookCommands = array_filter($commands, fn ($cmd) => str_contains($cmd, 'cache:clear') || str_contains($cmd, 'config:cache'));

    expect($hookCommands)->not->toBeEmpty();
});

test('executes hooks in release directory', function () {
    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: [],
        hooks: ['after_rollback' => ['ls -la']],
        healthCheck: [],
        sharedPaths: [],
        database: [],
        notifications: [],
    );

    $this->action->handle($config, $this->release);

    $commands = $this->ssh->executedCommands;
    $cdCommands = array_filter($commands, fn ($cmd) => str_contains($cmd, 'cd /var/www/app/releases/20250101-120000'));

    expect($cdCommands)->not->toBeEmpty();
});

test('handles empty hooks array', function () {
    $this->action->handle($this->config, $this->release);

    expect($this->logger->hasLog('info', 'Rollback completed successfully'))->toBeTrue();
});

test('handles missing after_rollback hooks', function () {
    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: [],
        hooks: ['before_deploy' => ['echo test']],
        healthCheck: [],
        sharedPaths: [],
        database: [],
        notifications: [],
    );

    $this->action->handle($config, $this->release);

    expect($this->logger->hasLog('info', 'Rollback completed successfully'))->toBeTrue();
});

test('creates symlink before executing hooks', function () {
    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: [],
        hooks: ['after_rollback' => ['echo done']],
        healthCheck: [],
        sharedPaths: [],
        database: [],
        notifications: [],
    );

    $this->action->handle($config, $this->release);

    $commands = $this->ssh->executedCommands;
    $symlinkIndex = null;
    $hookIndex = null;

    foreach ($commands as $index => $command) {
        if (str_contains($command, 'mv -Tf') && $symlinkIndex === null) {
            $symlinkIndex = $index;
        }

        if (str_contains($command, 'echo done') && $hookIndex === null) {
            $hookIndex = $index;
        }
    }

    expect($symlinkIndex)->toBeLessThan($hookIndex);
});

test('uses correct current symlink path', function () {
    $this->action->handle($this->config, $this->release);

    $commands = $this->ssh->executedCommands;
    $currentSymlink = array_filter($commands, fn ($cmd) => str_contains($cmd, '/var/www/app/current'));

    expect($currentSymlink)->not->toBeEmpty();
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

    $this->action->handle($config, $this->release);

    $commands = $this->ssh->executedCommands;
    $customPathCommands = array_filter($commands, fn ($cmd) => str_contains($cmd, '/custom/path/current'));

    expect($customPathCommands)->not->toBeEmpty();
});

test('logs release name', function () {
    $release = new Release(
        name: '20250202-150000',
        path: '/var/www/app/releases/20250202-150000',
        createdAt: new DateTimeImmutable,
    );

    $this->action->handle($this->config, $release);

    expect($this->logger->hasLog('info', 'Rolling back to release: 20250202-150000'))->toBeTrue();
});

test('executes multiple hooks in order', function () {
    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: [],
        hooks: ['after_rollback' => ['first command', 'second command', 'third command']],
        healthCheck: [],
        sharedPaths: [],
        database: [],
        notifications: [],
    );

    $this->action->handle($config, $this->release);

    $commands = $this->ssh->executedCommands;
    $firstIndex = null;
    $secondIndex = null;
    $thirdIndex = null;

    foreach ($commands as $index => $command) {
        if (str_contains($command, 'first command')) {
            $firstIndex = $index;
        }

        if (str_contains($command, 'second command')) {
            $secondIndex = $index;
        }

        if (str_contains($command, 'third command')) {
            $thirdIndex = $index;
        }
    }

    expect($firstIndex)->toBeLessThan($secondIndex);
    expect($secondIndex)->toBeLessThan($thirdIndex);
});
