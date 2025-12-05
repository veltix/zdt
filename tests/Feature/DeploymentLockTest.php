<?php

declare(strict_types=1);

use App\Actions\AcquireDeploymentLock;
use App\Actions\ReleaseDeploymentLock;
use App\Exceptions\DeploymentLockedException;
use App\Services\RemoteExecutor;
use App\ValueObjects\CommandResult;
use App\ValueObjects\DeploymentConfig;
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
});

test('deployment lock exception exists', function () {
    expect(class_exists(DeploymentLockedException::class))->toBeTrue();
});

test('deployment lock path is correctly determined', function () {
    expect($this->config->getDeployPath())->toBe('/var/www/app');
});

test('acquire lock creates lock file when no lock exists', function () {
    $sshMock = SshMockHelper::mockConnection();

    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) {
            if (str_contains($command, 'test -f')) {
                // Lock doesn't exist
                return new CommandResult(1, '', 'test -f');
            }

            // All other commands succeed
            return new CommandResult(0, '', $command);
        });

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new AcquireDeploymentLock($executor, $this->logger);

    $action->handle($this->config);

    expect(true)->toBeTrue();
});

test('acquire lock throws exception when recent lock exists', function () {
    $sshMock = SshMockHelper::mockConnection();

    // Mock lock file exists
    $sshMock->shouldReceive('execute')
        ->with('test -f /var/www/app/.deploy.lock')
        ->andReturn(new CommandResult(0, '', 'test -f'));

    // Mock getting lock age (60 seconds old, less than 3600 timeout)
    $currentTime = time();
    $lockTime = $currentTime - 60;

    $sshMock->shouldReceive('execute')
        ->with(Mockery::pattern('/stat.*\.deploy\.lock/'))
        ->andReturn(new CommandResult(0, (string) $lockTime, 'stat'));

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new AcquireDeploymentLock($executor, $this->logger);

    expect(fn () => $action->handle($this->config))
        ->toThrow(DeploymentLockedException::class);
});

test('acquire lock removes stale lock and creates new one', function () {
    $sshMock = SshMockHelper::mockConnection();

    // Mock getting lock age (2 hours old, older than 3600 second timeout)
    $currentTime = time();
    $lockTime = $currentTime - 7200;

    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) use ($lockTime) {
            if (str_contains($command, 'test -f')) {
                // Lock exists
                return new CommandResult(0, '', 'test -f');
            }

            if (str_contains($command, 'stat')) {
                // Return stale timestamp
                return new CommandResult(0, (string) $lockTime, 'stat');
            }

            // All other commands succeed (rm, echo)
            return new CommandResult(0, '', $command);
        });

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new AcquireDeploymentLock($executor, $this->logger);

    $action->handle($this->config);

    expect(true)->toBeTrue();
});

test('acquire lock throws exception when cannot determine lock age', function () {
    $sshMock = SshMockHelper::mockConnection();

    // Mock lock file exists
    $sshMock->shouldReceive('execute')
        ->with('test -f /var/www/app/.deploy.lock')
        ->andReturn(new CommandResult(0, '', 'test -f'));

    // Mock stat command fails
    $sshMock->shouldReceive('execute')
        ->with(Mockery::pattern('/stat.*\.deploy\.lock/'))
        ->andReturn(new CommandResult(1, '', 'stat'));

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new AcquireDeploymentLock($executor, $this->logger);

    expect(fn () => $action->handle($this->config))
        ->toThrow(DeploymentLockedException::class);
});

test('release lock removes lock file successfully', function () {
    $sshMock = SshMockHelper::mockConnection();

    $sshMock->shouldReceive('execute')
        ->with('rm -f /var/www/app/.deploy.lock')
        ->andReturn(new CommandResult(0, '', 'rm -f'));

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new ReleaseDeploymentLock($executor, $this->logger);

    $action->handle($this->config);

    expect(true)->toBeTrue();
});

test('release lock handles failure gracefully', function () {
    $sshMock = SshMockHelper::mockConnection();

    $sshMock->shouldReceive('execute')
        ->with('rm -f /var/www/app/.deploy.lock')
        ->andReturn(new CommandResult(1, 'Permission denied', 'rm -f'));

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new ReleaseDeploymentLock($executor, $this->logger);

    // Should not throw, just log warning
    $action->handle($this->config);

    expect(true)->toBeTrue();
});
