<?php

declare(strict_types=1);

use App\Actions\LinkSharedStorage;
use App\Actions\SyncEnvironmentFile;
use App\Services\FileSync;
use App\Services\RemoteExecutor;
use App\ValueObjects\CommandResult;
use App\ValueObjects\DeploymentConfig;
use App\ValueObjects\Release;
use Psr\Log\NullLogger;
use Tests\Helpers\SshMockHelper;

test('environment file is copied from shared to release', function () {
    $sshMock = SshMockHelper::mockConnection();

    $executedCommands = [];

    // Mock all execute calls
    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) use (&$executedCommands) {
            $executedCommands[] = $command;
            // Return success for test -f (env exists)
            if (str_contains($command, 'test -f')) {
                return new CommandResult(0, '', $command);
            }

            // Return success for cp
            return new CommandResult(0, '', $command);
        });

    $executor = new RemoteExecutor($sshMock, new NullLogger());
    $fileSync = new FileSync($sshMock, $executor, new NullLogger());
    $action = new SyncEnvironmentFile($fileSync, $executor, new NullLogger());

    $config = DeploymentConfig::fromArray([
        'server' => ['host' => 'test.com', 'username' => 'deployer'],
        'repository' => ['url' => 'git@github.com:test/repo.git'],
        'paths' => ['deploy_to' => '/var/www/app'],
    ]);

    $release = new Release(
        name: '20250101120000',
        path: '/var/www/app/releases/20250101120000',
        createdAt: new DateTimeImmutable(),
    );

    $action->handle($config, $release);

    // Verify test -f was called
    expect($executedCommands)->toContain('test -f /var/www/app/shared/.env');
    // Verify cp was called
    expect($executedCommands)->toContain('cp /var/www/app/shared/.env /var/www/app/releases/20250101120000/.env');
});

test('environment file sync warns if shared env does not exist', function () {
    $sshMock = SshMockHelper::mockConnection();

    $executedCommands = [];

    // Mock test -f to return false (file doesn't exist)
    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) use (&$executedCommands) {
            $executedCommands[] = $command;
            if (str_contains($command, 'test -f')) {
                return new CommandResult(1, '', $command);
            }

            return new CommandResult(0, '', $command);
        });

    $executor = new RemoteExecutor($sshMock, new NullLogger());
    $fileSync = new FileSync($sshMock, $executor, new NullLogger());
    $action = new SyncEnvironmentFile($fileSync, $executor, new NullLogger());

    $config = DeploymentConfig::fromArray([
        'server' => ['host' => 'test.com', 'username' => 'deployer'],
        'repository' => ['url' => 'git@github.com:test/repo.git'],
        'paths' => ['deploy_to' => '/var/www/app'],
    ]);

    $release = new Release(
        name: '20250101120000',
        path: '/var/www/app/releases/20250101120000',
        createdAt: new DateTimeImmutable(),
    );

    $action->handle($config, $release);

    // Test completes without error, just logs warning
    // Should NOT attempt to copy
    expect($executedCommands)->toContain('test -f /var/www/app/shared/.env');
    expect($executedCommands)->not->toContain('cp /var/www/app/shared/.env /var/www/app/releases/20250101120000/.env');
});

test('shared storage is symlinked to release', function () {
    $sshMock = SshMockHelper::mockConnection();

    $executedCommands = [];

    // Mock all execute calls
    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) use (&$executedCommands) {
            $executedCommands[] = $command;

            return new CommandResult(0, '', $command);
        });

    $executor = new RemoteExecutor($sshMock, new NullLogger());
    $fileSync = new FileSync($sshMock, $executor, new NullLogger());
    $action = new LinkSharedStorage($fileSync, $executor, new NullLogger());

    $config = DeploymentConfig::fromArray([
        'server' => ['host' => 'test.com', 'username' => 'deployer'],
        'repository' => ['url' => 'git@github.com:test/repo.git'],
        'paths' => ['deploy_to' => '/var/www/app'],
    ]);

    $release = new Release(
        name: '20250101120000',
        path: '/var/www/app/releases/20250101120000',
        createdAt: new DateTimeImmutable(),
    );

    $action->handle($config, $release);

    // Verify rm -rf was called
    expect($executedCommands)->toContain('rm -rf /var/www/app/releases/20250101120000/storage');
    // Verify ln -nfs was called for temp symlink
    expect($executedCommands)->toContain('ln -nfs /var/www/app/shared/storage /var/www/app/releases/20250101120000/storage.tmp');
    // Verify mv was called for atomic rename
    expect($executedCommands)->toContain('mv -Tf /var/www/app/releases/20250101120000/storage.tmp /var/www/app/releases/20250101120000/storage');
});

test('shared storage removes existing directory before symlinking', function () {
    $sshMock = SshMockHelper::mockConnection();

    $commandsExecuted = [];
    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) use (&$commandsExecuted) {
            $commandsExecuted[] = $command;

            return new CommandResult(0, '', $command);
        });

    $executor = new RemoteExecutor($sshMock, new NullLogger());
    $fileSync = new FileSync($sshMock, $executor, new NullLogger());
    $action = new LinkSharedStorage($fileSync, $executor, new NullLogger());

    $config = DeploymentConfig::fromArray([
        'server' => ['host' => 'test.com', 'username' => 'deployer'],
        'repository' => ['url' => 'git@github.com:test/repo.git'],
        'paths' => ['deploy_to' => '/var/www/app'],
    ]);

    $release = new Release(
        name: '20250101120000',
        path: '/var/www/app/releases/20250101120000',
        createdAt: new DateTimeImmutable(),
    );

    $action->handle($config, $release);

    // Should execute rm before ln
    $rmIndex = null;
    $lnIndex = null;

    foreach ($commandsExecuted as $index => $command) {
        if (str_contains($command, 'rm -rf')) {
            $rmIndex = $index;
        }
        if (str_contains($command, 'ln -nfs')) {
            $lnIndex = $index;
        }
    }

    expect($rmIndex)->not->toBeNull();
    expect($lnIndex)->not->toBeNull();
    expect($rmIndex)->toBeLessThan($lnIndex);
});
