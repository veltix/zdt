<?php

declare(strict_types=1);

use App\Actions\LinkCustomSharedPaths;
use App\Services\FileSync;
use App\Services\RemoteExecutor;
use App\ValueObjects\CommandResult;
use App\ValueObjects\DeploymentConfig;
use App\ValueObjects\Release;
use Psr\Log\NullLogger;
use Tests\Helpers\SshMockHelper;

test('deployment creates correct directory structure', function () {
    $sshMock = SshMockHelper::mockConnection();

    // Mock directory creation
    $sshMock->shouldReceive('execute')
        ->with(Mockery::pattern('/mkdir -p.*releases/'), Mockery::any())
        ->andReturn(new CommandResult(0, '', 'mkdir'));

    $sshMock->shouldReceive('execute')
        ->with(Mockery::pattern('/mkdir -p.*shared/'), Mockery::any())
        ->andReturn(new CommandResult(0, '', 'mkdir'));

    $sshMock->shouldReceive('execute')
        ->andReturn(new CommandResult(0, 'success', 'mocked'));

    expect($sshMock->isConnected())->toBeTrue();
});

test('custom shared paths are linked correctly', function () {
    $sshMock = SshMockHelper::mockConnection();
    $sshMock->shouldReceive('execute')
        ->andReturn(new CommandResult(0, 'success', 'mocked'));

    $executor = new RemoteExecutor($sshMock, new NullLogger());
    $fileSync = new FileSync($sshMock, $executor, new NullLogger());
    $action = new LinkCustomSharedPaths($fileSync, $executor, new NullLogger());

    $config = DeploymentConfig::fromArray([
        'server' => ['host' => 'test.com', 'username' => 'deployer'],
        'repository' => ['url' => 'git@github.com:test/repo.git'],
        'paths' => ['deploy_to' => '/var/www/app'],
        'shared_paths' => [
            'resources/lang' => 'lang',
            'public/uploads' => 'uploads',
        ],
    ]);

    $release = new Release(
        name: '20250101120000',
        path: '/var/www/app/releases/20250101120000',
        createdAt: new DateTimeImmutable(),
    );

    $action->handle($config, $release);

    expect($config->getSharedPaths())->toHaveKey('resources/lang');
    expect($config->getSharedPaths())->toHaveKey('public/uploads');
});

test('custom shared paths creates shared directory if not exists', function () {
    $sshMock = SshMockHelper::mockConnection();

    $executedCommands = [];

    // Mock all execute calls
    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) use (&$executedCommands) {
            $executedCommands[] = $command;
            // Return failure for test -e (doesn't exist)
            if (str_contains($command, 'test -e')) {
                return new CommandResult(1, '', $command);
            }

            // Return success for all other commands
            return new CommandResult(0, '', $command);
        });

    $executor = new RemoteExecutor($sshMock, new NullLogger());
    $fileSync = new FileSync($sshMock, $executor, new NullLogger());
    $action = new LinkCustomSharedPaths($fileSync, $executor, new NullLogger());

    $config = DeploymentConfig::fromArray([
        'server' => ['host' => 'test.com', 'username' => 'deployer'],
        'repository' => ['url' => 'git@github.com:test/repo.git'],
        'paths' => ['deploy_to' => '/var/www/app'],
        'shared_paths' => [
            'resources/lang' => 'lang',
        ],
    ]);

    $release = new Release(
        name: '20250101120000',
        path: '/var/www/app/releases/20250101120000',
        createdAt: new DateTimeImmutable(),
    );

    $action->handle($config, $release);

    // Verify mkdir commands were executed
    $mkdirCommands = array_filter($executedCommands, fn ($cmd) => str_contains($cmd, 'mkdir -p'));
    expect($mkdirCommands)->not->toBeEmpty();

    // Verify the shared directory was created
    expect($executedCommands)->toContain('mkdir -p /var/www/app/shared/lang');
});

test('deployment config includes shared paths from config', function () {
    $config = DeploymentConfig::fromArray([
        'server' => ['host' => 'test.com', 'username' => 'deployer'],
        'repository' => ['url' => 'git@github.com:test/repo.git'],
        'paths' => ['deploy_to' => '/var/www/app'],
        'shared_paths' => [
            'resources/lang' => 'lang',
            'public/uploads' => 'uploads',
            'config/custom.php' => 'config/custom.php',
        ],
    ]);

    $sharedPaths = $config->getSharedPaths();

    expect($sharedPaths)->toBeArray();
    expect($sharedPaths)->toHaveCount(3);
    expect($sharedPaths['resources/lang'])->toBe('lang');
    expect($sharedPaths['public/uploads'])->toBe('uploads');
    expect($sharedPaths['config/custom.php'])->toBe('config/custom.php');
});

test('deployment config handles empty shared paths', function () {
    $config = DeploymentConfig::fromArray([
        'server' => ['host' => 'test.com', 'username' => 'deployer'],
        'repository' => ['url' => 'git@github.com:test/repo.git'],
        'paths' => ['deploy_to' => '/var/www/app'],
    ]);

    $sharedPaths = $config->getSharedPaths();

    expect($sharedPaths)->toBeArray();
    expect($sharedPaths)->toBeEmpty();
});

test('link custom shared paths does nothing when no paths configured', function () {
    $sshMock = SshMockHelper::mockConnection();
    // Should not execute any commands
    $sshMock->shouldNotReceive('execute');

    $executor = new RemoteExecutor($sshMock, new NullLogger());
    $fileSync = new FileSync($sshMock, $executor, new NullLogger());
    $action = new LinkCustomSharedPaths($fileSync, $executor, new NullLogger());

    $config = DeploymentConfig::fromArray([
        'server' => ['host' => 'test.com', 'username' => 'deployer'],
        'repository' => ['url' => 'git@github.com:test/repo.git'],
        'paths' => ['deploy_to' => '/var/www/app'],
        'shared_paths' => [], // Empty
    ]);

    $release = new Release(
        name: '20250101120000',
        path: '/var/www/app/releases/20250101120000',
        createdAt: new DateTimeImmutable(),
    );

    $action->handle($config, $release);

    expect($config->getSharedPaths())->toBeEmpty();
});
