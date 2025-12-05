<?php

declare(strict_types=1);

use App\Actions\ValidateDeploymentConfig;

beforeEach(function () {
    // Clean up environment variables before each test
    putenv('DEPLOY_HOST');
    putenv('DEPLOY_USERNAME');
    putenv('DEPLOY_REPO_URL');
    putenv('DEPLOY_PATH');
    putenv('DEPLOY_BRANCH');
    putenv('DEPLOY_PORT');
    putenv('DEPLOY_KEY_PATH');
    putenv('DEPLOY_TIMEOUT');
    putenv('DEPLOY_KEEP_RELEASES');
    putenv('DEPLOY_USE_COMPOSER');
    putenv('DEPLOY_USE_NPM');
    putenv('DEPLOY_RUN_MIGRATIONS');
});

afterEach(function () {
    // Clean up any test .env.deployment files
    $testFile = getcwd().'/.env.deployment';
    if (file_exists($testFile)) {
        unlink($testFile);
    }
});

test('loads configuration from environment variables only', function () {
    putenv('DEPLOY_HOST=test.example.com');
    putenv('DEPLOY_USERNAME=deploy-user');
    putenv('DEPLOY_REPO_URL=https://github.com/test/repo.git');
    putenv('DEPLOY_PATH=/var/www/test-app');
    putenv('DEPLOY_BRANCH=staging');

    $validator = new ValidateDeploymentConfig;
    $config = $validator->handle('deploy.php');

    expect($config->server['host'])->toBe('test.example.com');
    expect($config->server['username'])->toBe('deploy-user');
    expect($config->repository['url'])->toBe('https://github.com/test/repo.git');
    expect($config->repository['branch'])->toBe('staging');
    expect($config->paths['deploy_to'])->toBe('/var/www/test-app');
});

test('loads configuration from .env.deployment file when no env vars set', function () {
    // Create a test .env.deployment file
    $envContent = <<<'ENV'
DEPLOY_HOST=from-env-file.com
DEPLOY_USERNAME=file-user
DEPLOY_REPO_URL=https://github.com/file/repo.git
DEPLOY_PATH=/var/www/file-app
DEPLOY_BRANCH=production
ENV;

    file_put_contents(getcwd().'/.env.deployment', $envContent);

    $validator = new ValidateDeploymentConfig;
    $config = $validator->handle('deploy.php');

    expect($config->server['host'])->toBe('from-env-file.com');
    expect($config->server['username'])->toBe('file-user');
    expect($config->repository['url'])->toBe('https://github.com/file/repo.git');
    expect($config->repository['branch'])->toBe('production');
    expect($config->paths['deploy_to'])->toBe('/var/www/file-app');
});

test('environment variables take precedence over .env.deployment file', function () {
    // Create .env.deployment file
    $envContent = <<<'ENV'
DEPLOY_HOST=from-env-file.com
DEPLOY_USERNAME=file-user
DEPLOY_REPO_URL=https://github.com/file/repo.git
DEPLOY_BRANCH=production
ENV;

    file_put_contents(getcwd().'/.env.deployment', $envContent);

    // Set environment variables (should override file)
    putenv('DEPLOY_HOST=from-env-var.com');
    putenv('DEPLOY_USERNAME=var-user');
    putenv('DEPLOY_REPO_URL=https://github.com/var/repo.git');

    $validator = new ValidateDeploymentConfig;
    $config = $validator->handle('deploy.php');

    // Should use env vars, not file
    expect($config->server['host'])->toBe('from-env-var.com');
    expect($config->server['username'])->toBe('var-user');
    expect($config->repository['url'])->toBe('https://github.com/var/repo.git');
});

test('ignores comments in .env.deployment file', function () {
    $envContent = <<<'ENV'
# This is a comment
DEPLOY_HOST=test.com
# Another comment
DEPLOY_USERNAME=testuser
DEPLOY_REPO_URL=https://github.com/test/repo.git
ENV;

    file_put_contents(getcwd().'/.env.deployment', $envContent);

    $validator = new ValidateDeploymentConfig;
    $config = $validator->handle('deploy.php');

    expect($config->server['host'])->toBe('test.com');
    expect($config->server['username'])->toBe('testuser');
});

test('ignores empty lines in .env.deployment file', function () {
    $envContent = <<<'ENV'
DEPLOY_HOST=test.com

DEPLOY_USERNAME=testuser

DEPLOY_REPO_URL=https://github.com/test/repo.git
ENV;

    file_put_contents(getcwd().'/.env.deployment', $envContent);

    $validator = new ValidateDeploymentConfig;
    $config = $validator->handle('deploy.php');

    expect($config->server['host'])->toBe('test.com');
    expect($config->server['username'])->toBe('testuser');
});

test('ignores invalid lines without equals sign in .env.deployment file', function () {
    $envContent = <<<'ENV'
DEPLOY_HOST=test.com
INVALID_LINE_WITHOUT_EQUALS
DEPLOY_USERNAME=testuser
DEPLOY_REPO_URL=https://github.com/test/repo.git
ENV;

    file_put_contents(getcwd().'/.env.deployment', $envContent);

    $validator = new ValidateDeploymentConfig;
    $config = $validator->handle('deploy.php');

    expect($config->server['host'])->toBe('test.com');
    expect($config->server['username'])->toBe('testuser');
});

test('handles values with equals signs in .env.deployment file', function () {
    $envContent = <<<'ENV'
DEPLOY_HOST=test.com
DEPLOY_USERNAME=testuser
DEPLOY_REPO_URL=https://example.com/path?param=value&other=data
ENV;

    file_put_contents(getcwd().'/.env.deployment', $envContent);

    $validator = new ValidateDeploymentConfig;
    $config = $validator->handle('deploy.php');

    expect($config->repository['url'])->toBe('https://example.com/path?param=value&other=data');
});

test('uses default values when neither env vars nor .env.deployment file exist', function () {
    $validator = new ValidateDeploymentConfig;
    $config = $validator->handle('nonexistent-config.php');

    // Should fall back to config('deploy')
    expect($config->server['host'])->not->toBeEmpty();
    expect($config->repository['url'])->not->toBeEmpty();
});

test('loads all configuration options from environment variables', function () {
    putenv('DEPLOY_HOST=test.com');
    putenv('DEPLOY_USERNAME=testuser');
    putenv('DEPLOY_REPO_URL=https://github.com/test/repo.git');
    putenv('DEPLOY_PATH=/var/www/test');
    putenv('DEPLOY_PORT=2222');
    putenv('DEPLOY_KEY_PATH=/custom/key/path');
    putenv('DEPLOY_TIMEOUT=600');
    putenv('DEPLOY_KEEP_RELEASES=10');
    putenv('DEPLOY_USE_COMPOSER=false');
    putenv('DEPLOY_USE_NPM=true');
    putenv('DEPLOY_RUN_MIGRATIONS=false');

    $validator = new ValidateDeploymentConfig;
    $config = $validator->handle('deploy.php');

    expect($config->server['port'])->toBe(2222);
    expect($config->server['key_path'])->toBe('/custom/key/path');
    expect($config->server['timeout'])->toBe(600);
    expect($config->options['keep_releases'])->toBe(10);
    expect($config->options['use_composer'])->toBe(false);
    expect($config->options['use_npm'])->toBe(true);
    expect($config->options['run_migrations'])->toBe(false);
});

test('env vars from .env.deployment do not override already set env vars', function () {
    // Set env var first
    putenv('DEPLOY_HOST=priority-host.com');

    // Create .env.deployment with different value
    $envContent = <<<'ENV'
DEPLOY_HOST=file-host.com
DEPLOY_USERNAME=testuser
DEPLOY_REPO_URL=https://github.com/test/repo.git
ENV;

    file_put_contents(getcwd().'/.env.deployment', $envContent);

    $validator = new ValidateDeploymentConfig;
    $config = $validator->handle('deploy.php');

    // Should use already-set env var, not file value
    expect($config->server['host'])->toBe('priority-host.com');
});

test('parses deployment hooks from environment variables', function () {
    putenv('DEPLOY_HOST=test.com');
    putenv('DEPLOY_USERNAME=testuser');
    putenv('DEPLOY_REPO_URL=https://github.com/test/repo.git');
    putenv("DEPLOY_HOOKS_AFTER_CLONE=composer install\nnpm ci");
    putenv("DEPLOY_HOOKS_BEFORE_ACTIVATE=php artisan migrate\nphp artisan cache:clear");

    $validator = new ValidateDeploymentConfig;
    $config = $validator->handle('deploy.php');

    $afterClone = $config->getHooks('after_clone');
    expect($afterClone)->toContain('composer install');
    expect($afterClone)->toContain('npm ci');

    $beforeActivate = $config->getHooks('before_activate');
    expect($beforeActivate)->toContain('php artisan migrate');
    expect($beforeActivate)->toContain('php artisan cache:clear');
});

test('uses default hooks when not specified in env vars', function () {
    putenv('DEPLOY_HOST=test.com');
    putenv('DEPLOY_USERNAME=testuser');
    putenv('DEPLOY_REPO_URL=https://github.com/test/repo.git');
    putenv('DEPLOY_HOOKS_AFTER_CLONE');

    $validator = new ValidateDeploymentConfig;
    $config = $validator->handle('deploy.php');

    $afterClone = $config->getHooks('after_clone');
    expect($afterClone)->toBeArray();
    expect(count($afterClone))->toBeGreaterThan(0);
    expect($afterClone[0])->toBe('composer install --no-dev --optimize-autoloader');
});
