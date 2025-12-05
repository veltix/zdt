<?php

declare(strict_types=1);

use App\Actions\PruneFailedReleases;
use App\Services\RemoteExecutor;
use App\ValueObjects\CommandResult;
use App\ValueObjects\DeploymentConfig;
use Tests\Helpers\FakeLogger;
use Tests\Helpers\FakeSshConnection;

beforeEach(function () {
    $this->logger = new FakeLogger;
    $this->ssh = new FakeSshConnection;
    $this->ssh->connect();
    $this->executor = new RemoteExecutor($this->ssh, $this->logger);
    $this->action = new PruneFailedReleases($this->executor, $this->logger);

    $this->config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: ['use_composer' => true],
        hooks: [],
        healthCheck: [],
        sharedPaths: [],
        database: [],
        notifications: [],
    );
});

test('removes releases without vendor directory when composer is enabled', function () {
    $this->ssh->setCommandResult(
        'readlink /var/www/app/current',
        new CommandResult(0, "/var/www/app/releases/20250103-120000\n", 'readlink')
    );
    $this->ssh->setCommandResult(
        'ls /var/www/app/releases',
        new CommandResult(0, "20250103-120000\n20250102-120000\n20250101-120000", 'ls')
    );
    $this->ssh->setCommandResult(
        'test -d /var/www/app/releases/20250102-120000/vendor',
        new CommandResult(1, '', 'test')
    );
    $this->ssh->setCommandResult(
        'rm -rf /var/www/app/releases/20250102-120000',
        new CommandResult(0, '', 'rm')
    );
    $this->ssh->setCommandResult(
        'test -d /var/www/app/releases/20250101-120000/vendor',
        new CommandResult(0, '', 'test')
    );

    $this->action->handle($this->config);

    expect($this->logger->hasLog('info', 'Pruning failed releases'))->toBeTrue();
    expect($this->logger->hasLog('info', 'Removing incomplete release: 20250102-120000'))->toBeTrue();
    expect($this->logger->hasLog('info', 'Failed releases pruned'))->toBeTrue();
});

test('does not remove current release', function () {
    $this->ssh->setCommandResult(
        'readlink /var/www/app/current',
        new CommandResult(0, "/var/www/app/releases/20250103-120000\n", 'readlink')
    );
    $this->ssh->setCommandResult(
        'ls /var/www/app/releases',
        new CommandResult(0, "20250103-120000\n20250102-120000", 'ls')
    );
    $this->ssh->setCommandResult(
        'test -d /var/www/app/releases/20250102-120000/vendor',
        new CommandResult(1, '', 'test')
    );
    $this->ssh->setCommandResult(
        'rm -rf /var/www/app/releases/20250102-120000',
        new CommandResult(0, '', 'rm')
    );

    $this->action->handle($this->config);

    $commands = $this->ssh->executedCommands;
    $testCurrentVendor = array_filter($commands, fn ($cmd) => str_contains($cmd, '20250103-120000/vendor'));

    expect($testCurrentVendor)->toBeEmpty();
});

test('skips pruning when composer is disabled', function () {
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

    $this->ssh->setCommandResult(
        'readlink /var/www/app/current',
        new CommandResult(0, "/var/www/app/releases/20250103-120000\n", 'readlink')
    );
    $this->ssh->setCommandResult(
        'ls /var/www/app/releases',
        new CommandResult(0, "20250103-120000\n20250102-120000", 'ls')
    );

    $this->action->handle($config);

    $commands = $this->ssh->executedCommands;
    $vendorChecks = array_filter($commands, fn ($cmd) => str_contains($cmd, 'vendor'));

    expect($vendorChecks)->toBeEmpty();
    expect($this->logger->hasLog('info', 'Pruning failed releases'))->toBeTrue();
    expect($this->logger->hasLog('info', 'Failed releases pruned'))->toBeTrue();
});

test('handles readlink failure gracefully', function () {
    $this->ssh->setCommandResult(
        'readlink /var/www/app/current',
        new CommandResult(1, '', 'readlink')
    );
    $this->ssh->setCommandResult(
        'ls /var/www/app/releases',
        new CommandResult(0, "20250103-120000\n20250102-120000", 'ls')
    );
    $this->ssh->setCommandResult(
        'test -d /var/www/app/releases/20250103-120000/vendor',
        new CommandResult(0, '', 'test')
    );
    $this->ssh->setCommandResult(
        'test -d /var/www/app/releases/20250102-120000/vendor',
        new CommandResult(0, '', 'test')
    );

    $this->action->handle($this->config);

    expect($this->logger->hasLog('info', 'Failed releases pruned'))->toBeTrue();
});

test('handles ls failure gracefully', function () {
    $this->ssh->setCommandResult(
        'readlink /var/www/app/current',
        new CommandResult(0, "/var/www/app/releases/20250103-120000\n", 'readlink')
    );
    $this->ssh->setCommandResult(
        'ls /var/www/app/releases',
        new CommandResult(1, '', 'ls')
    );

    $this->action->handle($this->config);

    expect($this->logger->hasLog('info', 'Pruning failed releases'))->toBeTrue();
    expect($this->ssh->executedCommands)->toHaveCount(2);
});

test('handles multiple failed releases', function () {
    $this->ssh->setCommandResult(
        'readlink /var/www/app/current',
        new CommandResult(0, "/var/www/app/releases/20250105-120000\n", 'readlink')
    );
    $this->ssh->setCommandResult(
        'ls /var/www/app/releases',
        new CommandResult(0, "20250105-120000\n20250104-120000\n20250103-120000\n20250102-120000\n20250101-120000", 'ls')
    );
    $this->ssh->setCommandResult(
        'test -d /var/www/app/releases/20250104-120000/vendor',
        new CommandResult(1, '', 'test')
    );
    $this->ssh->setCommandResult(
        'rm -rf /var/www/app/releases/20250104-120000',
        new CommandResult(0, '', 'rm')
    );
    $this->ssh->setCommandResult(
        'test -d /var/www/app/releases/20250103-120000/vendor',
        new CommandResult(0, '', 'test')
    );
    $this->ssh->setCommandResult(
        'test -d /var/www/app/releases/20250102-120000/vendor',
        new CommandResult(1, '', 'test')
    );
    $this->ssh->setCommandResult(
        'rm -rf /var/www/app/releases/20250102-120000',
        new CommandResult(0, '', 'rm')
    );
    $this->ssh->setCommandResult(
        'test -d /var/www/app/releases/20250101-120000/vendor',
        new CommandResult(0, '', 'test')
    );

    $this->action->handle($this->config);

    expect($this->logger->hasLog('info', 'Removing incomplete release: 20250104-120000'))->toBeTrue();
    expect($this->logger->hasLog('info', 'Removing incomplete release: 20250102-120000'))->toBeTrue();
});

test('does not fail when rm command fails', function () {
    $this->ssh->setCommandResult(
        'readlink /var/www/app/current',
        new CommandResult(0, "/var/www/app/releases/20250103-120000\n", 'readlink')
    );
    $this->ssh->setCommandResult(
        'ls /var/www/app/releases',
        new CommandResult(0, "20250103-120000\n20250102-120000", 'ls')
    );
    $this->ssh->setCommandResult(
        'test -d /var/www/app/releases/20250102-120000/vendor',
        new CommandResult(1, '', 'test')
    );
    $this->ssh->setCommandResult(
        'rm -rf /var/www/app/releases/20250102-120000',
        new CommandResult(1, 'Permission denied', 'rm')
    );

    $this->action->handle($this->config);

    expect($this->logger->hasLog('info', 'Failed releases pruned'))->toBeTrue();
});

test('handles empty release list', function () {
    $this->ssh->setCommandResult(
        'readlink /var/www/app/current',
        new CommandResult(0, "/var/www/app/releases/20250103-120000\n", 'readlink')
    );
    $this->ssh->setCommandResult(
        'ls /var/www/app/releases',
        new CommandResult(0, '', 'ls')
    );

    $this->action->handle($this->config);

    expect($this->logger->hasLog('info', 'Failed releases pruned'))->toBeTrue();
});

test('handles releases with whitespace in list', function () {
    $this->ssh->setCommandResult(
        'readlink /var/www/app/current',
        new CommandResult(0, "/var/www/app/releases/20250103-120000\n", 'readlink')
    );
    $this->ssh->setCommandResult(
        'ls /var/www/app/releases',
        new CommandResult(0, "  20250103-120000  \n  20250102-120000  \n  ", 'ls')
    );
    $this->ssh->setCommandResult(
        'test -d /var/www/app/releases/20250102-120000/vendor',
        new CommandResult(1, '', 'test')
    );
    $this->ssh->setCommandResult(
        'rm -rf /var/www/app/releases/20250102-120000',
        new CommandResult(0, '', 'rm')
    );

    $this->action->handle($this->config);

    expect($this->logger->hasLog('info', 'Removing incomplete release: 20250102-120000'))->toBeTrue();
});

test('uses correct path from config', function () {
    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'main'],
        paths: ['deploy_to' => '/custom/path'],
        options: ['use_composer' => true],
        hooks: [],
        healthCheck: [],
        sharedPaths: [],
        database: [],
        notifications: [],
    );

    $this->ssh->setCommandResult(
        'readlink /custom/path/current',
        new CommandResult(0, "/custom/path/releases/20250103-120000\n", 'readlink')
    );
    $this->ssh->setCommandResult(
        'ls /custom/path/releases',
        new CommandResult(0, "20250103-120000\n20250102-120000", 'ls')
    );
    $this->ssh->setCommandResult(
        'test -d /custom/path/releases/20250102-120000/vendor',
        new CommandResult(1, '', 'test')
    );
    $this->ssh->setCommandResult(
        'rm -rf /custom/path/releases/20250102-120000',
        new CommandResult(0, '', 'rm')
    );

    $this->action->handle($config);

    $commands = $this->ssh->executedCommands;
    $customPathCommands = array_filter($commands, fn ($cmd) => str_contains($cmd, '/custom/path/'));

    expect($customPathCommands)->not->toBeEmpty();
});

test('handles single release', function () {
    $this->ssh->setCommandResult(
        'readlink /var/www/app/current',
        new CommandResult(0, "/var/www/app/releases/20250103-120000\n", 'readlink')
    );
    $this->ssh->setCommandResult(
        'ls /var/www/app/releases',
        new CommandResult(0, '20250103-120000', 'ls')
    );

    $this->action->handle($this->config);

    $commands = $this->ssh->executedCommands;
    $rmCommands = array_filter($commands, fn ($cmd) => str_contains($cmd, 'rm -rf'));

    expect($rmCommands)->toBeEmpty();
    expect($this->logger->hasLog('info', 'Failed releases pruned'))->toBeTrue();
});

test('extracts current release basename correctly', function () {
    $this->ssh->setCommandResult(
        'readlink /var/www/app/current',
        new CommandResult(0, '/var/www/app/releases/20250103-120000', 'readlink')
    );
    $this->ssh->setCommandResult(
        'ls /var/www/app/releases',
        new CommandResult(0, "20250103-120000\n20250102-120000", 'ls')
    );
    $this->ssh->setCommandResult(
        'test -d /var/www/app/releases/20250102-120000/vendor',
        new CommandResult(1, '', 'test')
    );
    $this->ssh->setCommandResult(
        'rm -rf /var/www/app/releases/20250102-120000',
        new CommandResult(0, '', 'rm')
    );

    $this->action->handle($this->config);

    expect($this->logger->hasLog('info', 'Removing incomplete release: 20250102-120000'))->toBeTrue();
});
