<?php

declare(strict_types=1);

use App\Contracts\SshConnectionContract;
use App\ValueObjects\CommandResult;
use Illuminate\Support\Facades\Config;
use Tests\Fakes\FakeSshConnection;

beforeEach(function () {
    // Set default configuration with some flags disabled for "integration" testing without side effects
    Config::set('deploy.server.host', 'localhost');
    Config::set('deploy.server.port', 22);
    Config::set('deploy.server.username', 'test');
    Config::set('deploy.server.key_path', '/tmp/key');
    Config::set('deploy.server.timeout', 30);

    // Disable things that would hit external networks
    Config::set('deploy.health_check.enabled', false);
    Config::set('deploy.notifications.enabled', false);

    // Bind FakeSshConnection
    $this->fakeSsh = new FakeSshConnection();

    // Default responses for ValidateServerRequirements
    $this->fakeSsh->commandResponses["df -BM /var/www/test-app | awk 'NR==2 {print \$4}'"] = '1000M';
    $this->fakeSsh->commandResponses['test -d /var/www && test -w /var/www'] = 'success'; // Exit code 0 implied
    $this->fakeSsh->commandResponses["php -r 'echo PHP_VERSION;'"] = '8.3.0';
    $this->fakeSsh->commandResponses['which git'] = 'git found';
    $this->fakeSsh->commandResponses['which composer'] = 'composer found';

    // Default response for AcquireDeploymentLock (no lock exists)
    $this->fakeSsh->commandResponses['test -f /var/www/test-app/deploy.lock'] = new CommandResult(1, '', 'check lock');

    $this->app->instance(SshConnectionContract::class, $this->fakeSsh);
});

test('deploy command is registered', function () {
    $this->artisan('deploy --help')
        ->assertExitCode(0);
});

test('deploy command accepts branch option', function () {
    $this->artisan('deploy --help')
        ->expectsOutputToContain('--branch')
        ->assertExitCode(0);
});

test('deploy command accepts config option', function () {
    $this->artisan('deploy --help')
        ->expectsOutputToContain('--config')
        ->assertExitCode(0);
});

test('deploy command fails when SSH connection fails', function () {
    // We can simulate connection failure by throwing an exception on execute,
    // BUT the connection check usually happens first.
    // The DeployCommand calls $establishConnection->handle.
    // That calls $ssh->connect().
    // Our fake connect() works. But maybe we want to simulate failure?
    // FakeSshConnection implementation currently just sets a flag.
    // If we want to test failure of "EstablishSshConnection", we can't easily do it if it's a real class
    // unless the fake throws on connect().
    // Let's modify the fake to allow failing connect.

    // For now, let's assume valid connection, but failure on the first command (Validate Requirements usually).
    // Or we could mock the "EstablishSshConnection" ONLY if we really need to test connection strictness,
    // but the goal is to avoid mocks.
    // Does EstablishSshConnection do anything complex? It calls $ssh->connect().
    // So if $ssh->connect() throws, EstablishSshConnection throws.

    // We need to subclass or modify fake to allow throwing on connect.
    $this->fakeSsh->failOnConnect = true;

    // Check exit code
    $this->artisan('deploy --config=tests/fixtures/deploy-config-valid.php --no-interaction')
        ->assertExitCode(1);
});

test('deploy command outputs starting message', function () {
    // Fail immediately to stop flow
    $this->fakeSsh->throwOnCommand('/.*/', new Exception('Stop execution'));

    $this->artisan('deploy --config=tests/fixtures/deploy-config-valid.php --no-interaction')
        ->expectsOutputToContain('Starting zero downtime deployment');
    // We expect failure because of the exception
});

test('deploy command handles config validation failure', function () {
    // This uses real ValidateDeploymentConfig logic which reads the file.
    // The invalid fixture should cause ValidationException.

    // Ensure no commands are run
    $this->artisan('deploy --config=tests/fixtures/deploy-config-invalid.php --no-interaction')
        ->assertExitCode(1);

    expect($this->fakeSsh->commands)->toBeEmpty();
});

test('deploy command displays target server information', function () {
    // Fail early to limit output
    $this->fakeSsh->throwOnCommand('/.*/', new Exception('Stop'));

    $this->artisan('deploy --config=tests/fixtures/deploy-config-valid.php --no-interaction')
        ->expectsOutputToContain('Target: test.example.com')
        ->expectsOutputToContain('Repository: git@github.com:test/repo.git')
        ->expectsOutputToContain('Branch: main');
});

test('deploy command uses custom branch option', function () {
    $this->fakeSsh->throwOnCommand('/.*/', new Exception('Stop'));

    $this->artisan('deploy --config=tests/fixtures/deploy-config-valid.php --branch=develop --no-interaction')
        ->expectsOutputToContain('Branch: develop');
});

test('deploy command shows error message on failure', function () {
    $this->fakeSsh->throwOnCommand('/.*/', new RuntimeException('Generic failure'));

    $this->artisan('deploy --config=tests/fixtures/deploy-config-valid.php --no-interaction')
        ->expectsOutputToContain('Deployment failed: Generic failure')
        ->assertExitCode(1);
});

test('deploy command handles deployment exception with rollback prompt', function () {
    // We need to let it progress until it has created a release, then fail.
    // 1. Establish connection (passes)
    // 2. Validate Server Requirements (runs commands) -> We need the fake to pass these.
    //    It usually runs "php -v", "composer --version", etc.
    // 3. Acquire Lock (runs command) -> Default fake passes.
    // 4. Prepare Release (mkdir) -> Default fake passes.
    // 5. Clone Repo (git clone) -> WE MAKE THIS FAIL.

    // Let's configure the fake to fail on "git clone".
    $this->fakeSsh->failCommand('/git clone/', 1, 'Git clone failed');

    // We also need "Validate Server Requirements" to pass. checkConfig, etc.
    // The ValidateServerRequirements action runs: php -v, composer -v, etc.
    // Our fake returns success by default for unknown commands.

    // We need to ensure "mkdir -p ..." returns logic that it succeeded?
    // PrepareReleaseDirectory calls mkdir. Fake returns valid result by default.

    // So the flow:
    // ...
    // git clone ... -> Fails with Exit Code 1.
    // This throws RemoteExecutionException.
    // Catch block catches it.
    // Rollback prompt shown.

    $this->artisan('deploy --config=tests/fixtures/deploy-config-valid.php')
        ->expectsConfirmation('Attempt automatic rollback?', 'yes') // Say yes
        ->expectsOutputToContain('Deployment failed')
        ->expectsOutputToContain('Rolling back to previous release...')
        ->assertExitCode(1);
});

test('deploy command runs npm tasks and database backup when enabled', function () {
    // Fake SSH should verify NPM and Backup commands
    $this->fakeSsh->commandResponses['which npm'] = 'npm';
    $this->fakeSsh->commandResponses['which node'] = 'node';
    $this->fakeSsh->commandResponses['which mysqldump'] = 'mysqldump';

    // We expect npm install, npm run build, and mysqldump
    $executed = false;

    $this->artisan('deploy --config=tests/fixtures/deploy-config-comprehensive.php --no-interaction')
        ->expectsOutputToContain('Compiling assets')
        ->expectsOutputToContain('Backing up database')
        ->assertExitCode(0);

    // Verify commands in history
    $history = implode("\n", $this->fakeSsh->commands);
    expect($history)->toContain('npm ci')
        ->toContain('npm run build')
        ->toContain('mysqldump');
});

test('deploy command warns when notification fails in catch block', function () {
    // 1. Trigger deployment failure
    $this->fakeSsh->failCommand('/git clone/', 1, 'Clone failed');

    // 2. Setup notification failure
    // We need Http::fake to pass, but Logger to throw Error to hit the Catch block at line 226
    Http::fake([
        '*' => Http::response('ok', 200),
    ]);

    // Mock Logger to throw only when logging notification success
    $loggerMock = Mockery::mock(Psr\Log\LoggerInterface::class);
    $loggerMock->shouldIgnoreMissing();
    $loggerMock->shouldReceive('debug')->andReturnUsing(function ($msg) {
        if (str_contains($msg, 'Deployment notification sent')) {
            throw new Error('Logger exploded');
        }
    });

    // Bind Logger
    $this->app->instance(Psr\Log\LoggerInterface::class, $loggerMock);

    // Run command
    $this->artisan('deploy --config=tests/fixtures/deploy-config-comprehensive.php')
        ->expectsConfirmation('Attempt automatic rollback?', 'no')
        ->assertExitCode(1);
});

test('deploy command executes rollback successfully', function () {
    $this->fakeSsh->failCommand('/git clone/', 1, 'Clone failed');

    // Use regex keys to ensure matching regardless of flags/spacing
    // ls -t returns newest first. We want 01 (current) then 00 (previous)
    $this->fakeSsh->commandResponses['/ls -t .*releases/'] = "20240101-000001\n20240101-000000";
    $this->fakeSsh->commandResponses['/readlink .*current/'] = '/var/www/test-app/releases/20240101-000001';
    $this->fakeSsh->commandResponses['/test -d .*20240101-000000/'] = 'success';

    $this->artisan('deploy --config=tests/fixtures/deploy-config-valid.php')
        ->expectsConfirmation('Attempt automatic rollback?', 'yes')
        ->expectsOutputToContain('Rollback completed')
        ->assertExitCode(1); // Fails due to deploy failure, but rollback completes

    // Verify rollback commands
    $history = implode("\n", $this->fakeSsh->commands);
    expect($history)->toContain('ln -nfs /var/www/test-app/releases/20240101-000000');
});
