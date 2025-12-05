<?php

declare(strict_types=1);

use App\Contracts\SshConnectionContract;
use Illuminate\Support\Facades\Config;
use Tests\Fakes\FakeSshConnection;

beforeEach(function () {
    Config::set('deploy.server.host', 'localhost');
    Config::set('deploy.server.port', 22);
    Config::set('deploy.server.username', 'test');
    Config::set('deploy.server.key_path', '/tmp/key');
    Config::set('deploy.server.timeout', 30);

    $this->fakeSsh = new FakeSshConnection();
    $this->app->instance(SshConnectionContract::class, $this->fakeSsh);
});

test('list releases command is registered', function () {
    $this->artisan('releases:list --help')
        ->assertExitCode(0);
});

test('list releases command accepts config option', function () {
    $this->artisan('releases:list --help')
        ->expectsOutputToContain('--config')
        ->assertExitCode(0);
});

test('list releases command fails when SSH connection fails', function () {
    $this->fakeSsh->failOnConnect = true;

    $this->artisan('releases:list --config=tests/fixtures/deploy-config-valid.php')
        ->assertExitCode(1);
});

test('list releases command displays releases table', function () {
    // Setup fake responses
    $this->fakeSsh->commandResponses['readlink /var/www/test-app/current'] = '/var/www/test-app/releases/20230101020000';
    $this->fakeSsh->commandResponses["ls -lt /var/www/test-app/releases | grep '^d' | awk '{print \$9}'"] = "20230101020000\n20230101010000";

    $this->artisan('releases:list --config=tests/fixtures/deploy-config-valid.php')
        ->expectsOutputToContain('Listing releases...')
        ->expectsTable(
            ['Release', 'Status'],
            [
                ['20230101020000', '✓ Active'],
                ['20230101010000', ''],
            ]
        )
        ->assertExitCode(0);
});

test('list releases command shows warning when no releases found', function () {
    $this->fakeSsh->commandResponses['readlink /var/www/test-app/current'] = '';
    $this->fakeSsh->commandResponses["ls -lt /var/www/test-app/releases | grep '^d' | awk '{print \$9}'"] = '';

    $this->artisan('releases:list --config=tests/fixtures/deploy-config-valid.php')
        ->expectsOutputToContain('No releases found')
        ->assertExitCode(0);
});

test('list releases command outputs listing message', function () {
    $this->fakeSsh->throwOnCommand('/.*/', new Exception('Stop'));

    $this->artisan('releases:list --config=tests/fixtures/deploy-config-valid.php')
        ->expectsOutputToContain('Listing releases...');
});

test('list releases command shows error message on failure', function () {
    $this->fakeSsh->throwOnCommand('/.*/', new RuntimeException('Generic failure'));

    $this->artisan('releases:list --config=tests/fixtures/deploy-config-valid.php')
        ->expectsOutputToContain('Failed to list releases: Generic failure')
        ->assertExitCode(1);
});

test('list releases command displays current release info', function () {
    $this->fakeSsh->commandResponses['readlink /var/www/test-app/current'] = '/var/www/test-app/releases/20230101020000';
    $this->fakeSsh->commandResponses["ls -lt /var/www/test-app/releases | grep '^d' | awk '{print \$9}'"] = '20230101020000';

    $this->artisan('releases:list --config=tests/fixtures/deploy-config-valid.php')
        ->expectsOutputToContain('Total releases: 1')
        ->expectsOutputToContain('Current release: 20230101020000')
        ->assertExitCode(0);
});

test('list releases command handles config validation failure', function () {
    $this->artisan('releases:list --config=tests/fixtures/deploy-config-invalid.php')
        ->assertExitCode(1);
});
