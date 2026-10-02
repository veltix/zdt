<?php

declare(strict_types=1);

use App\Actions\BackupDatabase;
use App\Actions\EstablishSshConnection;
use App\Actions\ValidateDeploymentConfig;
use App\Actions\ValidateServerRequirements;
use App\Services\RemoteExecutor;
use App\ValueObjects\CommandResult;
use App\ValueObjects\DeploymentConfig;
use App\ValueObjects\Release;
use Tests\Helpers\FakeLogger;
use Tests\Helpers\FakeSshConnection;

beforeEach(function () {
    foreach (['DEPLOY_HOST', 'DEPLOY_USERNAME', 'DEPLOY_REPO_URL', 'DEPLOY_PORT', 'DEPLOY_KEY_PATH', 'DEPLOY_TIMEOUT', 'DEPLOY_PATH'] as $name) {
        putenv($name);
    }

    $this->projectConfig = tempnam(sys_get_temp_dir(), 'zdt').'.php';
    file_put_contents($this->projectConfig, <<<'PHP'
        <?php
        return [
            'server' => ['host' => 'project.example', 'port' => 2222, 'username' => 'deploy', 'key_path' => '/keys/project', 'timeout' => 900],
            'repository' => ['url' => 'git@github.com:acme/app.git', 'branch' => 'main'],
            'paths' => ['deploy_to' => '/var/www/app'],
        ];
        PHP);
});

afterEach(function () {
    @unlink($this->projectConfig);

    foreach (['DEPLOY_HOST', 'DEPLOY_USERNAME', 'DEPLOY_KEY_PATH'] as $name) {
        putenv($name);
    }
});

test('the project config file supplies the SSH server settings', function () {
    $config = (new ValidateDeploymentConfig)->handle($this->projectConfig);

    $credentials = $config->getServerCredentials();

    expect($credentials->host)->toBe('project.example')
        ->and($credentials->port)->toBe(2222)
        ->and($credentials->username)->toBe('deploy')
        ->and($credentials->keyPath)->toBe('/keys/project')
        ->and($credentials->timeout)->toBe(900);
});

test('DEPLOY_* server variables override the project config without replacing it', function () {
    putenv('DEPLOY_HOST=override.example');
    putenv('DEPLOY_USERNAME=other');
    putenv('DEPLOY_KEY_PATH=/keys/override');

    $config = (new ValidateDeploymentConfig)->handle($this->projectConfig);

    expect($config->server['host'])->toBe('override.example')
        ->and($config->server['username'])->toBe('other')
        ->and($config->server['key_path'])->toBe('/keys/override')
        ->and($config->server['port'])->toBe(2222)
        ->and($config->repository['url'])->toBe('git@github.com:acme/app.git');
});

test('the SSH connection uses the credentials of the loaded deployment config', function () {
    $ssh = new FakeSshConnection;
    $config = (new ValidateDeploymentConfig)->handle($this->projectConfig);

    (new EstablishSshConnection(new FakeLogger))->handle($ssh, $config);

    expect($ssh->usedCredentials?->host)->toBe('project.example')
        ->and($ssh->usedCredentials?->keyPath)->toBe('/keys/project')
        ->and($ssh->isConnected())->toBeTrue();
});

test('the permission check accepts a writable deploy path inside a read-only parent', function () {
    $ssh = new FakeSshConnection;
    $ssh->connect();
    $logger = new FakeLogger;
    $config = new DeploymentConfig(
        server: ['host' => 'app.example', 'username' => 'deploy'], repository: ['url' => 'git@github.com:acme/app.git'], paths: ['deploy_to' => '/var/www/app'], options: ['use_composer' => false],
        hooks: [], healthCheck: [], sharedPaths: [], database: [], notifications: [],
    );
    $ssh->setCommandResult("df -BM /var/www/app | awk 'NR==2 {print \$4}'", new CommandResult(0, "5000M\n", 'df'));
    $ssh->setCommandResult("php -r 'echo PHP_VERSION;'", new CommandResult(0, '8.5.0', 'php'));
    $ssh->setCommandResult('test -d /var/www && test -w /var/www', new CommandResult(1, '', 'test'));

    $result = (new ValidateServerRequirements(new RemoteExecutor($ssh, $logger), $logger))->handle($config);

    expect($result->checks['permissions'])->toBeTrue()
        ->and($ssh->executedCommands)->toContain('test -w /var/www/app || { test ! -e /var/www/app && test -d /var/www && test -w /var/www; }');
});

test('a database dump that fails before gzip fails the backup', function (string $connection) {
    $ssh = new FakeSshConnection;
    $ssh->connect();
    $logger = new FakeLogger;
    $config = new DeploymentConfig(
        server: ['host' => 'app.example', 'username' => 'deploy'], repository: ['url' => 'git@github.com:acme/app.git'], paths: ['deploy_to' => '/var/www/app'], options: [],
        hooks: [], healthCheck: [], sharedPaths: [],
        database: ['backup_enabled' => true, 'connection' => $connection, 'database' => 'app', 'username' => 'app', 'password' => 'secret'],
        notifications: [],
    );

    (new BackupDatabase(new RemoteExecutor($ssh, $logger), $logger))->handle($config, new Release('20260101000000', '/var/www/app/releases/20260101000000', new DateTimeImmutable));

    $dumps = array_values(array_filter($ssh->executedCommands, fn (string $command): bool => str_contains($command, '| gzip >')));

    expect($dumps)->toHaveCount(1)
        ->and($dumps[0])->toStartWith('set -o pipefail; ');
})->with(['pgsql', 'mysql']);
