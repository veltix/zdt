<?php

declare(strict_types=1);

use App\Actions\ValidateRollbackTarget;
use App\Exceptions\RollbackException;
use App\Services\RemoteExecutor;
use App\ValueObjects\CommandResult;
use App\ValueObjects\Release;
use Tests\Helpers\FakeLogger;
use Tests\Helpers\FakeSshConnection;

beforeEach(function () {
    $this->logger = new FakeLogger;
    $this->ssh = new FakeSshConnection;
    $this->ssh->connect();
    $this->executor = new RemoteExecutor($this->ssh, $this->logger);
    $this->action = new ValidateRollbackTarget($this->executor, $this->logger);

    $this->release = new Release(
        name: '20250101-120000',
        path: '/var/www/app/releases/20250101-120000',
        createdAt: new DateTimeImmutable,
    );
});

test('validates rollback target successfully', function () {
    $this->ssh->setCommandResult(
        'test -d /var/www/app/releases/20250101-120000',
        new CommandResult(0, '', 'test')
    );
    $this->ssh->setCommandResult(
        'test -f /var/www/app/releases/20250101-120000/index.php || test -f /var/www/app/releases/20250101-120000/artisan',
        new CommandResult(0, '', 'test')
    );

    $this->action->handle($this->release);

    expect($this->logger->hasLog('info', 'Validating rollback target: 20250101-120000'))->toBeTrue();
    expect($this->logger->hasLog('info', 'Rollback target is valid'))->toBeTrue();
});

test('throws exception when target directory does not exist', function () {
    $this->ssh->setCommandResult(
        'test -d /var/www/app/releases/20250101-120000',
        new CommandResult(1, '', 'test')
    );

    expect(fn () => $this->action->handle($this->release))
        ->toThrow(RollbackException::class, 'Target release not found: 20250101-120000');
});

test('throws exception when target is incomplete', function () {
    $this->ssh->setCommandResult(
        'test -d /var/www/app/releases/20250101-120000',
        new CommandResult(0, '', 'test')
    );
    $this->ssh->setCommandResult(
        'test -f /var/www/app/releases/20250101-120000/index.php || test -f /var/www/app/releases/20250101-120000/artisan',
        new CommandResult(1, '', 'test')
    );

    expect(fn () => $this->action->handle($this->release))
        ->toThrow(RollbackException::class, 'Target release appears to be incomplete: 20250101-120000');
});

test('validates release with index.php file', function () {
    $this->ssh->setCommandResult(
        'test -d /var/www/app/releases/20250101-120000',
        new CommandResult(0, '', 'test')
    );
    $this->ssh->setCommandResult(
        'test -f /var/www/app/releases/20250101-120000/index.php || test -f /var/www/app/releases/20250101-120000/artisan',
        new CommandResult(0, '', 'test')
    );

    $this->action->handle($this->release);

    expect($this->logger->hasLog('info', 'Rollback target is valid'))->toBeTrue();
});

test('validates release with artisan file', function () {
    $this->ssh->setCommandResult(
        'test -d /var/www/app/releases/20250101-120000',
        new CommandResult(0, '', 'test')
    );
    $this->ssh->setCommandResult(
        'test -f /var/www/app/releases/20250101-120000/index.php || test -f /var/www/app/releases/20250101-120000/artisan',
        new CommandResult(0, '', 'test')
    );

    $this->action->handle($this->release);

    expect($this->logger->hasLog('info', 'Rollback target is valid'))->toBeTrue();
});

test('validates release with different path', function () {
    $release = new Release(
        name: '20250202-120000',
        path: '/custom/path/releases/20250202-120000',
        createdAt: new DateTimeImmutable,
    );

    $this->ssh->setCommandResult(
        'test -d /custom/path/releases/20250202-120000',
        new CommandResult(0, '', 'test')
    );
    $this->ssh->setCommandResult(
        'test -f /custom/path/releases/20250202-120000/index.php || test -f /custom/path/releases/20250202-120000/artisan',
        new CommandResult(0, '', 'test')
    );

    $this->action->handle($release);

    expect($this->logger->hasLog('info', 'Validating rollback target: 20250202-120000'))->toBeTrue();
    expect($this->logger->hasLog('info', 'Rollback target is valid'))->toBeTrue();
});

test('logs correct release name', function () {
    $release = new Release(
        name: '20250303-150000',
        path: '/var/www/app/releases/20250303-150000',
        createdAt: new DateTimeImmutable,
    );

    $this->ssh->setCommandResult(
        'test -d /var/www/app/releases/20250303-150000',
        new CommandResult(0, '', 'test')
    );
    $this->ssh->setCommandResult(
        'test -f /var/www/app/releases/20250303-150000/index.php || test -f /var/www/app/releases/20250303-150000/artisan',
        new CommandResult(0, '', 'test')
    );

    $this->action->handle($release);

    expect($this->logger->hasLog('info', 'Validating rollback target: 20250303-150000'))->toBeTrue();
});

test('executes directory check before file check', function () {
    $this->ssh->setCommandResult(
        'test -d /var/www/app/releases/20250101-120000',
        new CommandResult(0, '', 'test')
    );
    $this->ssh->setCommandResult(
        'test -f /var/www/app/releases/20250101-120000/index.php || test -f /var/www/app/releases/20250101-120000/artisan',
        new CommandResult(0, '', 'test')
    );

    $this->action->handle($this->release);

    expect($this->ssh->executedCommands[0])->toContain('test -d');
    expect($this->ssh->executedCommands[1])->toContain('test -f');
});

test('does not check files when directory does not exist', function () {
    $this->ssh->setCommandResult(
        'test -d /var/www/app/releases/20250101-120000',
        new CommandResult(1, '', 'test')
    );

    try {
        $this->action->handle($this->release);
    } catch (RollbackException $e) {
        expect($this->ssh->executedCommands)->toHaveCount(1);
        expect($this->ssh->executedCommands[0])->toContain('test -d');
    }
});

test('includes release name in exception messages', function () {
    $this->ssh->setCommandResult(
        'test -d /var/www/app/releases/20250101-120000',
        new CommandResult(1, '', 'test')
    );

    try {
        $this->action->handle($this->release);
        expect(false)->toBeTrue();
    } catch (RollbackException $e) {
        expect($e->getMessage())->toContain('20250101-120000');
    }
});
