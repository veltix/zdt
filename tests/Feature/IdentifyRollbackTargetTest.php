<?php

declare(strict_types=1);

use App\Actions\IdentifyRollbackTarget;
use App\Exceptions\RollbackException;
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
    $this->action = new IdentifyRollbackTarget($this->executor, $this->logger);

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

test('identifies previous release as rollback target', function () {
    $this->ssh->setCommandResult(
        'readlink /var/www/app/current',
        new CommandResult(0, "/var/www/app/releases/20250103-120000\n", 'readlink')
    );
    $this->ssh->setCommandResult(
        'ls -t /var/www/app/releases',
        new CommandResult(0, "20250103-120000\n20250102-120000\n20250101-120000", 'ls')
    );

    $target = $this->action->handle($this->config);

    expect($target->name)->toBe('20250102-120000');
    expect($target->path)->toBe('/var/www/app/releases/20250102-120000');
    expect($this->logger->hasLog('info', 'Current release: 20250103-120000'))->toBeTrue();
    expect($this->logger->hasLog('info', 'Rollback target identified: 20250102-120000'))->toBeTrue();
});

test('identifies specific target release when provided', function () {
    $this->ssh->setCommandResult(
        'readlink /var/www/app/current',
        new CommandResult(0, "/var/www/app/releases/20250103-120000\n", 'readlink')
    );

    $target = $this->action->handle($this->config, '20250101-120000');

    expect($target->name)->toBe('20250101-120000');
    expect($target->path)->toBe('/var/www/app/releases/20250101-120000');
});

test('skips current release and finds next older release', function () {
    $this->ssh->setCommandResult(
        'readlink /var/www/app/current',
        new CommandResult(0, "/var/www/app/releases/20250105-120000\n", 'readlink')
    );
    $this->ssh->setCommandResult(
        'ls -t /var/www/app/releases',
        new CommandResult(0, "20250105-120000\n20250104-120000\n20250103-120000\n20250102-120000", 'ls')
    );

    $target = $this->action->handle($this->config);

    expect($target->name)->toBe('20250104-120000');
    expect($target->path)->toBe('/var/www/app/releases/20250104-120000');
});

test('throws exception when no previous release exists', function () {
    $this->ssh->setCommandResult(
        'readlink /var/www/app/current',
        new CommandResult(0, "/var/www/app/releases/20250101-120000\n", 'readlink')
    );
    $this->ssh->setCommandResult(
        'ls -t /var/www/app/releases',
        new CommandResult(0, '20250101-120000', 'ls')
    );

    expect(fn () => $this->action->handle($this->config))
        ->toThrow(RollbackException::class, 'No previous release found to rollback to');
});

test('throws exception when only current release exists', function () {
    $this->ssh->setCommandResult(
        'readlink /var/www/app/current',
        new CommandResult(0, "/var/www/app/releases/20250101-120000\n", 'readlink')
    );
    $this->ssh->setCommandResult(
        'ls -t /var/www/app/releases',
        new CommandResult(0, "20250101-120000\n", 'ls')
    );

    expect(fn () => $this->action->handle($this->config))
        ->toThrow(RollbackException::class, 'No previous release found');
});

test('handles multiple releases correctly', function () {
    $this->ssh->setCommandResult(
        'readlink /var/www/app/current',
        new CommandResult(0, "/var/www/app/releases/20250106-120000\n", 'readlink')
    );
    $this->ssh->setCommandResult(
        'ls -t /var/www/app/releases',
        new CommandResult(
            0,
            "20250106-120000\n20250105-120000\n20250104-120000\n20250103-120000\n20250102-120000\n20250101-120000",
            'ls'
        )
    );

    $target = $this->action->handle($this->config);

    expect($target->name)->toBe('20250105-120000');
    expect($target->path)->toBe('/var/www/app/releases/20250105-120000');
});

test('handles release names with extra whitespace', function () {
    $this->ssh->setCommandResult(
        'readlink /var/www/app/current',
        new CommandResult(0, "/var/www/app/releases/20250103-120000\n", 'readlink')
    );
    $this->ssh->setCommandResult(
        'ls -t /var/www/app/releases',
        new CommandResult(0, "  20250103-120000  \n  20250102-120000  \n  20250101-120000  ", 'ls')
    );

    $target = $this->action->handle($this->config);

    expect($target->name)->toBe('20250102-120000');
    expect($target->path)->toBe('/var/www/app/releases/20250102-120000');
});

test('handles empty lines in release list', function () {
    $this->ssh->setCommandResult(
        'readlink /var/www/app/current',
        new CommandResult(0, "/var/www/app/releases/20250103-120000\n", 'readlink')
    );
    $this->ssh->setCommandResult(
        'ls -t /var/www/app/releases',
        new CommandResult(0, "20250103-120000\n\n20250102-120000\n\n20250101-120000\n", 'ls')
    );

    $target = $this->action->handle($this->config);

    expect($target->name)->toBe('20250102-120000');
    expect($target->path)->toBe('/var/www/app/releases/20250102-120000');
});

test('returns target with correct path structure', function () {
    $this->ssh->setCommandResult(
        'readlink /var/www/app/current',
        new CommandResult(0, "/var/www/app/releases/20250103-120000\n", 'readlink')
    );
    $this->ssh->setCommandResult(
        'ls -t /var/www/app/releases',
        new CommandResult(0, "20250103-120000\n20250102-120000", 'ls')
    );

    $target = $this->action->handle($this->config);

    expect($target->path)->toStartWith('/var/www/app/releases/');
    expect($target->path)->toEndWith($target->name);
});

test('throws exception when current release not in list', function () {
    $this->ssh->setCommandResult(
        'readlink /var/www/app/current',
        new CommandResult(0, "/var/www/app/releases/20250999-120000\n", 'readlink')
    );
    $this->ssh->setCommandResult(
        'ls -t /var/www/app/releases',
        new CommandResult(0, "20250103-120000\n20250102-120000\n20250101-120000", 'ls')
    );

    expect(fn () => $this->action->handle($this->config))
        ->toThrow(RollbackException::class);
});

test('specific target only requires readlink command', function () {
    $this->ssh->setCommandResult(
        'readlink /var/www/app/current',
        new CommandResult(0, "/var/www/app/releases/20250103-120000\n", 'readlink')
    );

    $target = $this->action->handle($this->config, '20250101-120000');

    expect($target->name)->toBe('20250101-120000');
    expect($target->path)->toBe('/var/www/app/releases/20250101-120000');
    expect($this->ssh->executedCommands)->toHaveCount(1);
    expect($this->ssh->executedCommands[0])->toBe('readlink /var/www/app/current');
});

test('extracts current release name from full path', function () {
    $this->ssh->setCommandResult(
        'readlink /var/www/app/current',
        new CommandResult(0, '/var/www/app/releases/20250103-120000', 'readlink')
    );
    $this->ssh->setCommandResult(
        'ls -t /var/www/app/releases',
        new CommandResult(0, "20250103-120000\n20250102-120000", 'ls')
    );

    $target = $this->action->handle($this->config);

    expect($target->name)->toBe('20250102-120000');
    expect($this->logger->hasLog('info', 'Current release: 20250103-120000'))->toBeTrue();
});

test('handles single previous release', function () {
    $this->ssh->setCommandResult(
        'readlink /var/www/app/current',
        new CommandResult(0, "/var/www/app/releases/20250102-120000\n", 'readlink')
    );
    $this->ssh->setCommandResult(
        'ls -t /var/www/app/releases',
        new CommandResult(0, "20250102-120000\n20250101-120000", 'ls')
    );

    $target = $this->action->handle($this->config);

    expect($target->name)->toBe('20250101-120000');
});

test('uses correct paths from config', function () {
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

    $this->ssh->setCommandResult(
        'readlink /custom/path/current',
        new CommandResult(0, "/custom/path/releases/20250102-120000\n", 'readlink')
    );
    $this->ssh->setCommandResult(
        'ls -t /custom/path/releases',
        new CommandResult(0, "20250102-120000\n20250101-120000", 'ls')
    );

    $target = $this->action->handle($config);

    expect($target->path)->toBe('/custom/path/releases/20250101-120000');
});
