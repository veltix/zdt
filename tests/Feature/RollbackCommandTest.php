<?php

declare(strict_types=1);

use App\Contracts\SshConnectionContract;
use Illuminate\Support\Facades\Config;
use Tests\Fakes\FakeSshConnection;

beforeEach(function () {
    // Set default configuration
    Config::set('deploy.server.host', 'localhost');
    Config::set('deploy.server.port', 22);
    Config::set('deploy.server.username', 'test');
    Config::set('deploy.server.key_path', '/tmp/key');
    Config::set('deploy.server.timeout', 30);

    // Bind FakeSshConnection
    $this->fakeSsh = new FakeSshConnection();
    $this->app->instance(SshConnectionContract::class, $this->fakeSsh);
});

test('rollback command is registered', function () {
    $this->artisan('rollback --help')
        ->assertExitCode(0);
});

test('rollback command accepts config option', function () {
    $this->artisan('rollback --help')
        ->expectsOutputToContain('--config')
        ->assertExitCode(0);
});

test('rollback command accepts release option', function () {
    $this->artisan('rollback --help')
        ->expectsOutputToContain('--release')
        ->assertExitCode(0);
});

test('rollback command fails when SSH connection fails', function () {
    // Modify fake to throw on connect
    $this->fakeSsh->failOnConnect = true;

    $this->artisan('rollback --config=tests/fixtures/deploy-config-valid.php')
        ->assertExitCode(1);
});

test('rollback command outputs starting message', function () {
    $this->fakeSsh->throwOnCommand('/.*/', new Exception('Stop execution'));

    $this->artisan('rollback --config=tests/fixtures/deploy-config-valid.php')
        ->expectsOutputToContain('Starting rollback');
});

test('rollback command displays target server information', function () {
    $this->fakeSsh->throwOnCommand('/.*/', new Exception('Stop'));

    $this->artisan('rollback --config=tests/fixtures/deploy-config-valid.php')
        ->expectsOutputToContain('Target server: test.example.com');
});

test('rollback command shows error message on failure', function () {
    $this->fakeSsh->throwOnCommand('/.*/', new RuntimeException('Generic failure'));

    $this->artisan('rollback --config=tests/fixtures/deploy-config-valid.php')
        ->expectsOutputToContain('Rollback failed: Generic failure')
        ->assertExitCode(1);
});

test('rollback command handles config validation failure', function () {
    $this->artisan('rollback --config=tests/fixtures/deploy-config-invalid.php')
        ->assertExitCode(1);

    expect($this->fakeSsh->commands)->toBeEmpty();
});

test('rollback command displays rollback target', function () {
    // IdentifyRollbackTarget uses:
    // 1. readlink current -> "20230101020000" (Current)
    // 2. ls releases -> "20230101020000\n20230101010000" (Current + Previous)
    // 3. Rollback to 20230101010000

    // Commands executed by IdentifyRollbackTarget/RemoteExecutor:
    // "readlink -f /var/www/current" -> Return path to current
    // "ls -1t /var/www/releases" -> Return list of releases

    // Fix: We need to set expectations in FakeSshConnection
    // But FakeSshConnection relies on pattern matching.

    // Command 1: readlink
    // Response: /var/www/test-app/releases/20230101020000
    $this->fakeSsh->commandResponses['readlink /var/www/test-app/current'] = '/var/www/test-app/releases/20230101020000';

    // Command 2: ls
    // Response: 20230101020000\n20230101010000
    $this->fakeSsh->commandResponses['ls -t /var/www/test-app/releases'] = "20230101020000\n20230101010000";

    // Command 3: Validate Rollback Target checks if Directory exists
    // /var/www/test-app/releases/20230101010000
    $this->fakeSsh->expectDirectoryExists('/var/www/test-app/releases/20230101010000');

    $this->artisan('rollback --config=tests/fixtures/deploy-config-valid.php')
        ->expectsOutputToContain('Rolling back to: 20230101010000')
        ->expectsOutputToContain('Rollback completed successfully!')
        ->assertExitCode(0);
});

test('rollback command uses custom release option', function () {
    // If specific release is provided, IdentifyRollbackTarget checks if it exists in list.
    // Or maybe just verifies existence?
    // IdentifyRollbackTarget::handle logic:
    // If target provided:
    //   List releases.
    //   Check if target is in list.
    //   Return target.

    $this->fakeSsh->commandResponses['ls -t /var/www/test-app/releases'] = "20230101020000\n20230101010000\n20230101000000";

    $this->fakeSsh->expectDirectoryExists('/var/www/test-app/releases/20230101000000');

    $this->artisan('rollback --config=tests/fixtures/deploy-config-valid.php --release=20230101000000')
        ->expectsOutputToContain('Rolling back to: 20230101000000')
        ->assertExitCode(0);
});
