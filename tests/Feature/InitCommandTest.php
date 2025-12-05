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

    // Default responses for ValidateServerRequirements (uses test-app path from fixture)
    $this->fakeSsh->commandResponses["df -BM /var/www/test-app | awk 'NR==2 {print \$4}'"] = '1000M';
    $this->fakeSsh->commandResponses['test -d /var/www && test -w /var/www'] = 'success';
    $this->fakeSsh->commandResponses["php -r 'echo PHP_VERSION;'"] = '8.3.0';
    $this->fakeSsh->commandResponses['which git'] = 'git found';
    $this->fakeSsh->commandResponses['which composer'] = 'composer found';

    $this->app->instance(SshConnectionContract::class, $this->fakeSsh);
});

test('init command is registered', function () {
    $this->artisan('deploy:init --help')
        ->assertExitCode(0);
});

test('init command accepts config option', function () {
    $this->artisan('deploy:init --help')
        ->expectsOutputToContain('--config')
        ->assertExitCode(0);
});

test('init command fails when SSH connection fails', function () {
    $this->fakeSsh->failOnConnect = true;

    $this->artisan('deploy:init --config=tests/fixtures/deploy-config-valid.php')
        ->assertExitCode(1);
});

test('init command outputs initialization message', function () {
    $this->fakeSsh->throwOnCommand('/.*/', new Exception('Stop'));

    $this->artisan('deploy:init --config=tests/fixtures/deploy-config-valid.php')
        ->expectsOutputToContain('Initializing deployment structure...');
});

test('init command displays target server information', function () {
    $this->fakeSsh->throwOnCommand('/.*/', new Exception('Stop'));

    $this->artisan('deploy:init --config=tests/fixtures/deploy-config-valid.php')
        ->expectsOutputToContain('Target: test.example.com')
        ->expectsOutputToContain('Deploy path: /var/www/test-app');
});

test('init command shows error message on failure', function () {
    $this->fakeSsh->throwOnCommand('/.*/', new RuntimeException('Generic failure'));

    $this->artisan('deploy:init --config=tests/fixtures/deploy-config-valid.php')
        ->expectsOutputToContain('Initialization failed: Generic failure')
        ->assertExitCode(1);
});

test('init command handles config validation failure', function () {
    $this->artisan('deploy:init --config=tests/fixtures/deploy-config-invalid.php')
        ->assertExitCode(1);
});

test('init command creates directory structure successfully', function () {
    $this->artisan('deploy:init --config=tests/fixtures/deploy-config-valid.php')
        ->expectsOutputToContain('Server initialized successfully')
        ->assertExitCode(0);

    // Verify directories created
    $executedCommands = implode(' ', $this->fakeSsh->commands);
    expect($executedCommands)->toContain('mkdir -p /var/www/test-app/releases');
    expect($executedCommands)->toContain('mkdir -p /var/www/test-app/shared/storage/app');
    expect($executedCommands)->toContain('mkdir -p /var/www/test-app/shared/storage/logs');
    expect($executedCommands)->toContain('mkdir -p /var/www/test-app/.zdt');
});

test('init command displays next steps after success', function () {
    $this->artisan('deploy:init --config=tests/fixtures/deploy-config-valid.php')
        ->expectsOutputToContain('Next steps:')
        ->expectsOutputToContain('1. Create .env file at:')
        ->expectsOutputToContain('2. Run your first deployment:')
        ->assertExitCode(0);
});
