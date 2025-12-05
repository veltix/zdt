<?php

declare(strict_types=1);

use App\Contracts\SshConnectionContract;
use App\Services\PhpseclibSshConnection;
use App\ValueObjects\ServerCredentials;
use Illuminate\Support\Facades\Config;

it('binds SshConnectionContract to PhpseclibSshConnection', function () {
    expect($this->app->make(SshConnectionContract::class))->toBeInstanceOf(PhpseclibSshConnection::class);
});

it('binds ServerCredentials using environment variables', function () {
    putenv('DEPLOY_HOST=env-host');
    putenv('DEPLOY_PORT=2222');
    putenv('DEPLOY_USERNAME=env-user');
    putenv('DEPLOY_KEY_PATH=/env/key');
    putenv('DEPLOY_TIMEOUT=100');

    // Force re-resolution
    $this->app->forgetInstance(ServerCredentials::class);

    $credentials = $this->app->make(ServerCredentials::class);

    expect($credentials->host)->toBe('env-host')
        ->and($credentials->port)->toBe(2222)
        ->and($credentials->username)->toBe('env-user')
        ->and($credentials->keyPath)->toBe('/env/key')
        ->and($credentials->timeout)->toBe(100);

    // Cleanup
    putenv('DEPLOY_HOST');
    putenv('DEPLOY_PORT');
    putenv('DEPLOY_USERNAME');
    putenv('DEPLOY_KEY_PATH');
    putenv('DEPLOY_TIMEOUT');
});

it('binds ServerCredentials using configuration values', function () {
    Config::set('deploy.server.host', 'config-host');
    Config::set('deploy.server.port', 3333);
    Config::set('deploy.server.username', 'config-user');
    Config::set('deploy.server.key_path', '/config/key');
    Config::set('deploy.server.timeout', 200);

    // Force re-resolution
    $this->app->forgetInstance(ServerCredentials::class);

    $credentials = $this->app->make(ServerCredentials::class);

    expect($credentials->host)->toBe('config-host')
        ->and($credentials->port)->toBe(3333)
        ->and($credentials->username)->toBe('config-user')
        ->and($credentials->keyPath)->toBe('/config/key')
        ->and($credentials->timeout)->toBe(200);
});

it('binds ServerCredentials using default values', function () {
    // Clear any potential config or env interference
    Config::set('deploy', []);

    putenv('DEPLOY_HOST');
    putenv('DEPLOY_PORT');
    putenv('DEPLOY_USERNAME');
    putenv('DEPLOY_KEY_PATH');
    putenv('DEPLOY_TIMEOUT');

    // Force re-resolution
    $this->app->forgetInstance(ServerCredentials::class);

    $credentials = $this->app->make(ServerCredentials::class);

    expect($credentials->host)->toBe('localhost')
        ->and($credentials->port)->toBe(22)
        ->and($credentials->username)->toBe('deployer')
        ->and($credentials->keyPath)->toContain('.ssh/id_rsa')
        ->and($credentials->timeout)->toBe(300);
});
