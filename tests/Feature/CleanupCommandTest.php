<?php

declare(strict_types=1);

use App\Contracts\SshConnectionContract;
use App\Exceptions\SshConnectionException;

test('cleanup command is registered', function () {
    $this->artisan('releases:cleanup --help')
        ->assertExitCode(0);
});

test('cleanup command fails when SSH connection fails', function () {
    $sshMock = Mockery::mock(SshConnectionContract::class);

    $sshMock->shouldReceive('connect')
        ->andThrow(new SshConnectionException('Connection failed'));

    $this->app->instance(SshConnectionContract::class, $sshMock);

    $this->artisan('releases:cleanup --config=tests/fixtures/deploy-config-valid.php')
        ->assertExitCode(1);
});

test('cleanup command accepts keep option', function () {
    $sshMock = Mockery::mock(SshConnectionContract::class);

    $sshMock->shouldReceive('connect')
        ->andThrow(new SshConnectionException('Connection failed'));

    $this->app->instance(SshConnectionContract::class, $sshMock);

    $this->artisan('releases:cleanup --keep=10 --config=tests/fixtures/deploy-config-valid.php')
        ->assertExitCode(1);
});
test('cleanup command executes successfully', function () {
    $sshMock = Mockery::mock(SshConnectionContract::class);
    $sshMock->shouldReceive('connect')->once();
    $sshMock->shouldReceive('disconnect')->once();

    // Allow any execute command and return success
    $sshMock->shouldReceive('execute')
        ->andReturnUsing(function ($command) {
            return new App\ValueObjects\CommandResult(0, '', $command);
        });

    $this->app->instance(SshConnectionContract::class, $sshMock);

    // We do NOT mock the Action classes because they are final.
    // We let the Container resolve the real classes, which will use our mocked SSH connection.

    $this->artisan('releases:cleanup --config=tests/fixtures/deploy-config-valid.php')
        ->expectsOutput('Cleanup completed successfully!')
        ->assertExitCode(0);
});
