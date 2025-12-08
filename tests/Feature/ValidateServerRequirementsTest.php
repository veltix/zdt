<?php

declare(strict_types=1);

use App\Actions\ValidateServerRequirements;
use App\Exceptions\ValidationException;
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
    $this->action = new ValidateServerRequirements($this->executor, $this->logger);

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

test('validates all server requirements successfully', function () {
    $this->ssh->setCommandResult(
        "df -BM /var/www/app | awk 'NR==2 {print \$4}'",
        new CommandResult(0, "1000M\n", 'df')
    );
    $this->ssh->setCommandResult(
        'test -d /var/www && test -w /var/www',
        new CommandResult(0, '', 'test')
    );
    $this->ssh->setCommandResult(
        "php -r 'echo PHP_VERSION;'",
        new CommandResult(0, '8.3.5', 'php')
    );
    $this->ssh->setCommandResult(
        'which git',
        new CommandResult(0, '/usr/bin/git', 'which')
    );
    $this->ssh->setCommandResult(
        'which composer',
        new CommandResult(0, '/usr/bin/composer', 'which')
    );

    $result = $this->action->handle($this->config);

    expect($result->passed)->toBeTrue();
    expect($result->checks['disk_space'])->toBeTrue();
    expect($result->checks['permissions'])->toBeTrue();
    expect($result->checks['php_version'])->toBeTrue();
    expect($result->checks['git'])->toBeTrue();
    expect($result->checks['composer'])->toBeTrue();
    expect($this->logger->hasLog('info', 'Validating server requirements'))->toBeTrue();
    expect($this->logger->hasLog('info', 'All server requirements validated'))->toBeTrue();
});

test('throws exception when disk space is insufficient', function () {
    $this->ssh->setCommandResult(
        "df -BM /var/www/app | awk 'NR==2 {print \$4}'",
        new CommandResult(0, "400M\n", 'df')
    );
    $this->ssh->setCommandResult(
        'test -d /var/www && test -w /var/www',
        new CommandResult(0, '', 'test')
    );
    $this->ssh->setCommandResult(
        "php -r 'echo PHP_VERSION;'",
        new CommandResult(0, '8.3.5', 'php')
    );
    $this->ssh->setCommandResult(
        'which git',
        new CommandResult(0, '/usr/bin/git', 'which')
    );
    $this->ssh->setCommandResult(
        'which composer',
        new CommandResult(0, '/usr/bin/composer', 'which')
    );

    expect(fn () => $this->action->handle($this->config))
        ->toThrow(ValidationException::class, 'disk_space');
});

test('throws exception when disk space check fails', function () {
    $this->ssh->setCommandResult(
        "df -BM /var/www/app | awk 'NR==2 {print \$4}'",
        new CommandResult(1, 'error', 'df')
    );
    $this->ssh->setCommandResult(
        'test -d /var/www && test -w /var/www',
        new CommandResult(0, '', 'test')
    );
    $this->ssh->setCommandResult(
        "php -r 'echo PHP_VERSION;'",
        new CommandResult(0, '8.3.5', 'php')
    );
    $this->ssh->setCommandResult(
        'which git',
        new CommandResult(0, '/usr/bin/git', 'which')
    );
    $this->ssh->setCommandResult(
        'which composer',
        new CommandResult(0, '/usr/bin/composer', 'which')
    );

    expect(fn () => $this->action->handle($this->config))
        ->toThrow(ValidationException::class, 'disk_space');
});

test('throws exception when permissions check fails', function () {
    $this->ssh->setCommandResult(
        "df -BM /var/www/app | awk 'NR==2 {print \$4}'",
        new CommandResult(0, "1000M\n", 'df')
    );
    $this->ssh->setCommandResult(
        'test -d /var/www && test -w /var/www',
        new CommandResult(1, '', 'test')
    );
    $this->ssh->setCommandResult(
        "php -r 'echo PHP_VERSION;'",
        new CommandResult(0, '8.3.5', 'php')
    );
    $this->ssh->setCommandResult(
        'which git',
        new CommandResult(0, '/usr/bin/git', 'which')
    );
    $this->ssh->setCommandResult(
        'which composer',
        new CommandResult(0, '/usr/bin/composer', 'which')
    );

    expect(fn () => $this->action->handle($this->config))
        ->toThrow(ValidationException::class, 'permissions');
});

test('throws exception when PHP version is too old', function () {
    $this->ssh->setCommandResult(
        "df -BM /var/www/app | awk 'NR==2 {print \$4}'",
        new CommandResult(0, "1000M\n", 'df')
    );
    $this->ssh->setCommandResult(
        'test -d /var/www && test -w /var/www',
        new CommandResult(0, '', 'test')
    );
    $this->ssh->setCommandResult(
        "php -r 'echo PHP_VERSION;'",
        new CommandResult(0, '8.1.0', 'php')
    );
    $this->ssh->setCommandResult(
        'which git',
        new CommandResult(0, '/usr/bin/git', 'which')
    );
    $this->ssh->setCommandResult(
        'which composer',
        new CommandResult(0, '/usr/bin/composer', 'which')
    );

    expect(fn () => $this->action->handle($this->config))
        ->toThrow(ValidationException::class, 'php_version');
});

test('throws exception when PHP check fails', function () {
    $this->ssh->setCommandResult(
        "df -BM /var/www/app | awk 'NR==2 {print \$4}'",
        new CommandResult(0, "1000M\n", 'df')
    );
    $this->ssh->setCommandResult(
        'test -d /var/www && test -w /var/www',
        new CommandResult(0, '', 'test')
    );
    $this->ssh->setCommandResult(
        "php -r 'echo PHP_VERSION;'",
        new CommandResult(1, '', 'php')
    );
    $this->ssh->setCommandResult(
        'which git',
        new CommandResult(0, '/usr/bin/git', 'which')
    );
    $this->ssh->setCommandResult(
        'which composer',
        new CommandResult(0, '/usr/bin/composer', 'which')
    );

    expect(fn () => $this->action->handle($this->config))
        ->toThrow(ValidationException::class, 'php_version');
});

test('throws exception when git is not available', function () {
    $this->ssh->setCommandResult(
        "df -BM /var/www/app | awk 'NR==2 {print \$4}'",
        new CommandResult(0, "1000M\n", 'df')
    );
    $this->ssh->setCommandResult(
        'test -d /var/www && test -w /var/www',
        new CommandResult(0, '', 'test')
    );
    $this->ssh->setCommandResult(
        "php -r 'echo PHP_VERSION;'",
        new CommandResult(0, '8.3.5', 'php')
    );
    $this->ssh->setCommandResult(
        'which git',
        new CommandResult(1, '', 'which')
    );
    $this->ssh->setCommandResult(
        'which composer',
        new CommandResult(0, '/usr/bin/composer', 'which')
    );

    expect(fn () => $this->action->handle($this->config))
        ->toThrow(ValidationException::class, 'git');
});

test('throws exception when composer is not available', function () {
    $this->ssh->setCommandResult(
        "df -BM /var/www/app | awk 'NR==2 {print \$4}'",
        new CommandResult(0, "1000M\n", 'df')
    );
    $this->ssh->setCommandResult(
        'test -d /var/www && test -w /var/www',
        new CommandResult(0, '', 'test')
    );
    $this->ssh->setCommandResult(
        "php -r 'echo PHP_VERSION;'",
        new CommandResult(0, '8.3.5', 'php')
    );
    $this->ssh->setCommandResult(
        'which git',
        new CommandResult(0, '/usr/bin/git', 'which')
    );
    $this->ssh->setCommandResult(
        'which composer',
        new CommandResult(1, '', 'which')
    );

    expect(fn () => $this->action->handle($this->config))
        ->toThrow(ValidationException::class, 'composer');
});

test('skips composer check when not needed', function () {
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
        "df -BM /var/www/app | awk 'NR==2 {print \$4}'",
        new CommandResult(0, "1000M\n", 'df')
    );
    $this->ssh->setCommandResult(
        'test -d /var/www && test -w /var/www',
        new CommandResult(0, '', 'test')
    );
    $this->ssh->setCommandResult(
        "php -r 'echo PHP_VERSION;'",
        new CommandResult(0, '8.3.5', 'php')
    );
    $this->ssh->setCommandResult(
        'which git',
        new CommandResult(0, '/usr/bin/git', 'which')
    );

    $result = $this->action->handle($config);

    expect($result->passed)->toBeTrue();
    expect($result->checks['composer'])->toBeTrue();
});

test('logs debug information for disk space', function () {
    $this->ssh->setCommandResult(
        "df -BM /var/www/app | awk 'NR==2 {print \$4}'",
        new CommandResult(0, "1500M\n", 'df')
    );
    $this->ssh->setCommandResult(
        'test -d /var/www && test -w /var/www',
        new CommandResult(0, '', 'test')
    );
    $this->ssh->setCommandResult(
        "php -r 'echo PHP_VERSION;'",
        new CommandResult(0, '8.3.5', 'php')
    );
    $this->ssh->setCommandResult(
        'which git',
        new CommandResult(0, '/usr/bin/git', 'which')
    );
    $this->ssh->setCommandResult(
        'which composer',
        new CommandResult(0, '/usr/bin/composer', 'which')
    );

    $this->action->handle($this->config);

    expect($this->logger->hasLog('debug', 'Available disk space: 1500MB'))->toBeTrue();
});

test('logs debug information for PHP version', function () {
    $this->ssh->setCommandResult(
        "df -BM /var/www/app | awk 'NR==2 {print \$4}'",
        new CommandResult(0, "1000M\n", 'df')
    );
    $this->ssh->setCommandResult(
        'test -d /var/www && test -w /var/www',
        new CommandResult(0, '', 'test')
    );
    $this->ssh->setCommandResult(
        "php -r 'echo PHP_VERSION;'",
        new CommandResult(0, '8.3.2', 'php')
    );
    $this->ssh->setCommandResult(
        'which git',
        new CommandResult(0, '/usr/bin/git', 'which')
    );
    $this->ssh->setCommandResult(
        'which composer',
        new CommandResult(0, '/usr/bin/composer', 'which')
    );

    $this->action->handle($this->config);

    expect($this->logger->hasLog('debug', 'PHP version: 8.3.2'))->toBeTrue();
});

test('accepts PHP version exactly at minimum', function () {
    $this->ssh->setCommandResult(
        "df -BM /var/www/app | awk 'NR==2 {print \$4}'",
        new CommandResult(0, "1000M\n", 'df')
    );
    $this->ssh->setCommandResult(
        'test -d /var/www && test -w /var/www',
        new CommandResult(0, '', 'test')
    );
    $this->ssh->setCommandResult(
        "php -r 'echo PHP_VERSION;'",
        new CommandResult(0, '8.2.0', 'php')
    );
    $this->ssh->setCommandResult(
        'which git',
        new CommandResult(0, '/usr/bin/git', 'which')
    );
    $this->ssh->setCommandResult(
        'which composer',
        new CommandResult(0, '/usr/bin/composer', 'which')
    );

    $result = $this->action->handle($this->config);

    expect($result->passed)->toBeTrue();
    expect($result->checks['php_version'])->toBeTrue();
});

test('accepts disk space exactly at minimum', function () {
    $this->ssh->setCommandResult(
        "df -BM /var/www/app | awk 'NR==2 {print \$4}'",
        new CommandResult(0, "500M\n", 'df')
    );
    $this->ssh->setCommandResult(
        'test -d /var/www && test -w /var/www',
        new CommandResult(0, '', 'test')
    );
    $this->ssh->setCommandResult(
        "php -r 'echo PHP_VERSION;'",
        new CommandResult(0, '8.3.5', 'php')
    );
    $this->ssh->setCommandResult(
        'which git',
        new CommandResult(0, '/usr/bin/git', 'which')
    );
    $this->ssh->setCommandResult(
        'which composer',
        new CommandResult(0, '/usr/bin/composer', 'which')
    );

    $result = $this->action->handle($this->config);

    expect($result->passed)->toBeTrue();
    expect($result->checks['disk_space'])->toBeTrue();
});

test('throws exception with multiple failed checks', function () {
    $this->ssh->setCommandResult(
        "df -BM /var/www/app | awk 'NR==2 {print \$4}'",
        new CommandResult(0, "400M\n", 'df')
    );
    $this->ssh->setCommandResult(
        'test -d /var/www && test -w /var/www',
        new CommandResult(1, '', 'test')
    );
    $this->ssh->setCommandResult(
        "php -r 'echo PHP_VERSION;'",
        new CommandResult(0, '8.3.5', 'php')
    );
    $this->ssh->setCommandResult(
        'which git',
        new CommandResult(1, '', 'which')
    );
    $this->ssh->setCommandResult(
        'which composer',
        new CommandResult(0, '/usr/bin/composer', 'which')
    );

    try {
        $this->action->handle($this->config);
        expect(false)->toBeTrue();
    } catch (ValidationException $e) {
        expect($e->getMessage())->toContain('disk_space');
        expect($e->getMessage())->toContain('permissions');
        expect($e->getMessage())->toContain('git');
    }
});
