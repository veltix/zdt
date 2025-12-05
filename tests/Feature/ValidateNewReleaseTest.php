<?php

declare(strict_types=1);

use App\Actions\ValidateNewRelease;
use App\Exceptions\HealthCheckFailedException;
use App\Services\RemoteExecutor;
use App\ValueObjects\CommandResult;
use App\ValueObjects\DeploymentConfig;
use App\ValueObjects\Release;
use Psr\Log\NullLogger;
use Tests\Helpers\SshMockHelper;

beforeEach(function () {
    $this->logger = new NullLogger;
    $this->release = new Release(
        name: '20250101-120000',
        path: '/var/www/app/releases/20250101-120000',
        createdAt: new DateTimeImmutable,
    );
});

test('validate new release passes when all checks succeed', function () {
    $sshMock = SshMockHelper::mockConnection();

    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) {
            // All test commands succeed
            return new CommandResult(0, '', $command);
        });

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new ValidateNewRelease($executor, $this->logger);

    $config = new DeploymentConfig(
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

    $result = $action->handle($config, $this->release);

    expect($result->healthy)->toBeTrue();
});

test('validate new release throws exception when release directory missing', function () {
    $sshMock = SshMockHelper::mockConnection();

    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) {
            if (str_contains($command, 'test -d') && str_contains($command, 'releases')) {
                // Release directory doesn't exist
                return new CommandResult(1, '', $command);
            }

            return new CommandResult(0, '', $command);
        });

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new ValidateNewRelease($executor, $this->logger);

    $config = new DeploymentConfig(
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

    expect(fn () => $action->handle($config, $this->release))
        ->toThrow(HealthCheckFailedException::class, 'Release directory does not exist');
});

test('validate new release checks vendor directory when composer enabled', function () {
    $sshMock = SshMockHelper::mockConnection();

    $executedCommands = [];

    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) use (&$executedCommands) {
            $executedCommands[] = $command;

            return new CommandResult(0, '', $command);
        });

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new ValidateNewRelease($executor, $this->logger);

    $config = new DeploymentConfig(
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

    $result = $action->handle($config, $this->release);

    // Should check vendor directory
    $vendorCheck = array_filter($executedCommands, fn ($cmd) => str_contains($cmd, 'test -d') && str_contains($cmd, 'vendor'));

    expect($vendorCheck)->not->toBeEmpty();
    expect($result->healthy)->toBeTrue();
});

test('validate new release skips vendor check when composer disabled', function () {
    $sshMock = SshMockHelper::mockConnection();

    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) {
            // Don't expect vendor directory check
            if (str_contains($command, 'vendor')) {
                throw new Exception('Should not check vendor when composer disabled');
            }

            return new CommandResult(0, '', $command);
        });

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new ValidateNewRelease($executor, $this->logger);

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

    $result = $action->handle($config, $this->release);

    expect($result->healthy)->toBeTrue();
});

test('validate new release checks for env file', function () {
    $sshMock = SshMockHelper::mockConnection();

    $executedCommands = [];

    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) use (&$executedCommands) {
            $executedCommands[] = $command;

            return new CommandResult(0, '', $command);
        });

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new ValidateNewRelease($executor, $this->logger);

    $config = new DeploymentConfig(
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

    $action->handle($config, $this->release);

    // Should check for .env file
    $envCheck = array_filter($executedCommands, fn ($cmd) => str_contains($cmd, 'test -f') && str_contains($cmd, '.env'));

    expect($envCheck)->not->toBeEmpty();
});

test('validate new release warns when env file is missing', function () {
    $sshMock = SshMockHelper::mockConnection();
    $loggerMock = Mockery::mock(Psr\Log\LoggerInterface::class);
    $loggerMock->shouldIgnoreMissing();
    // Expect warning
    $loggerMock->shouldReceive('warning')
        ->with('.env file not found in release')
        ->once();

    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) {
            // Env file check fails
            if (str_contains($command, 'test -f') && str_contains($command, '.env')) {
                return new CommandResult(1, '', $command);
            }

            return new CommandResult(0, '', $command);
        });

    $executor = new RemoteExecutor($sshMock, $loggerMock);
    $action = new ValidateNewRelease($executor, $loggerMock);

    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'repo', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: [],
        hooks: [],
        healthCheck: [],
        sharedPaths: [],
        database: [],
        notifications: [],
    );

    $action->handle($config, $this->release);

    expect(true)->toBeTrue();
});

test('validate new release throws exception when vendor directory missing', function () {
    $sshMock = SshMockHelper::mockConnection();

    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) {
            // Vendor check fails
            if (str_contains($command, 'test -d') && str_contains($command, 'vendor')) {
                return new CommandResult(1, '', $command);
            }

            return new CommandResult(0, '', $command);
        });

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new ValidateNewRelease($executor, $this->logger);

    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'repo', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: ['use_composer' => true],
        hooks: [],
        healthCheck: [],
        sharedPaths: [],
        database: [],
        notifications: [],
    );

    expect(fn () => $action->handle($config, $this->release))
        ->toThrow(HealthCheckFailedException::class, 'Composer dependencies not installed');
});
