<?php

declare(strict_types=1);

use App\Actions\RemoveOldReleases;
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
        options: ['keep_releases' => 3],
        hooks: [],
        healthCheck: [],
        sharedPaths: [],
        database: [],
        notifications: [],
    );
});

test('remove old releases keeps specified number of releases', function () {
    $sshMock = SshMockHelper::mockConnection();

    // Mock listing releases (5 releases, keep 3, so 2 should be removed)
    $releaseList = "20250101080000\n20250101090000";

    $sshMock->shouldReceive('execute')
        ->with('ls -t /var/www/app/releases | tail -n +4')
        ->andReturn(new CommandResult(0, $releaseList, 'ls'));

    // Mock removal of old releases
    $sshMock->shouldReceive('execute')
        ->with(Mockery::pattern('/rm -rf/'))
        ->times(2)
        ->andReturn(new CommandResult(0, '', 'rm'));

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new RemoveOldReleases($executor, $this->logger);

    $action->handle($this->config);

    expect(true)->toBeTrue();
});

test('remove old releases does nothing when fewer than keep limit', function () {
    $sshMock = SshMockHelper::mockConnection();

    // Mock listing releases (empty result - no old releases to remove)
    $sshMock->shouldReceive('execute')
        ->with('ls -t /var/www/app/releases | tail -n +4')
        ->andReturn(new CommandResult(0, '', 'ls'));

    // Should not call rm
    $sshMock->shouldNotReceive('execute')
        ->with(Mockery::pattern('/rm -rf/'));

    $executor = new RemoteExecutor($sshMock, $this->logger);
    $action = new RemoveOldReleases($executor, $this->logger);

    $action->handle($this->config);

    expect(true)->toBeTrue();
});

test('remove old releases uses correct keep releases value', function () {
    $configWith5 = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: ['keep_releases' => 5],
        hooks: [],
        healthCheck: [],
        sharedPaths: [],
        database: [],
        notifications: [],
    );

    expect($configWith5->getKeepReleases())->toBe(5);
});

test('remove old releases defaults to 5 when not specified', function () {
    $configDefault = new DeploymentConfig(
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

    expect($configDefault->getKeepReleases())->toBe(5);
});
