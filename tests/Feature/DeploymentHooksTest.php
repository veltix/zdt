<?php

declare(strict_types=1);

use App\ValueObjects\DeploymentConfig;

test('deployment hooks from environment variables are parsed correctly', function () {
    putenv("DEPLOY_HOOKS_AFTER_CLONE=composer install --no-dev\nnpm ci --production");
    putenv('DEPLOY_HOST=test.example.com');
    putenv('DEPLOY_USERNAME=testuser');
    putenv('DEPLOY_REPO_URL=git@github.com:test/repo.git');
    putenv('DEPLOY_PATH=/var/www/test-app');

    $config = require __DIR__.'/../../config/deploy.php';

    expect($config['hooks']['after_clone'])->toBeArray();
    expect($config['hooks']['after_clone'])->toContain('composer install --no-dev');
    expect($config['hooks']['after_clone'])->toContain('npm ci --production');

    // Clean up
    putenv('DEPLOY_HOOKS_AFTER_CLONE');
    putenv('DEPLOY_HOST');
    putenv('DEPLOY_USERNAME');
    putenv('DEPLOY_REPO_URL');
    putenv('DEPLOY_PATH');
});

test('empty lines in hooks are filtered out', function () {
    putenv("DEPLOY_HOOKS_BEFORE_CLONE=echo 'test'\n\necho 'test2'");
    putenv('DEPLOY_HOST=test.example.com');
    putenv('DEPLOY_USERNAME=testuser');
    putenv('DEPLOY_REPO_URL=git@github.com:test/repo.git');
    putenv('DEPLOY_PATH=/var/www/test-app');

    $config = require __DIR__.'/../../config/deploy.php';

    expect($config['hooks']['before_clone'])->toBeArray();
    // Empty lines should be filtered out by array_filter
    foreach ($config['hooks']['before_clone'] as $hook) {
        expect($hook)->not->toBeEmpty();
    }

    // Clean up
    putenv('DEPLOY_HOOKS_BEFORE_CLONE');
    putenv('DEPLOY_HOST');
    putenv('DEPLOY_USERNAME');
    putenv('DEPLOY_REPO_URL');
    putenv('DEPLOY_PATH');
});

test('deployment config provides all hook stages', function () {
    $config = DeploymentConfig::fromArray([
        'server' => ['host' => 'test.com', 'username' => 'deployer'],
        'repository' => ['url' => 'git@github.com:test/repo.git'],
        'paths' => ['deploy_to' => '/var/www/app'],
        'hooks' => [
            'before_clone' => ['echo before'],
            'after_clone' => ['echo after clone'],
            'before_activate' => ['echo before activate'],
            'after_activate' => ['echo after activate'],
            'after_rollback' => ['echo after rollback'],
        ],
    ]);

    expect($config->getHooks('before_clone'))->toContain('echo before');
    expect($config->getHooks('after_clone'))->toContain('echo after clone');
    expect($config->getHooks('before_activate'))->toContain('echo before activate');
    expect($config->getHooks('after_activate'))->toContain('echo after activate');
    expect($config->getHooks('after_rollback'))->toContain('echo after rollback');
});

test('deployment config returns empty array for undefined hook stage', function () {
    $config = DeploymentConfig::fromArray([
        'server' => ['host' => 'test.com', 'username' => 'deployer'],
        'repository' => ['url' => 'git@github.com:test/repo.git'],
        'paths' => ['deploy_to' => '/var/www/app'],
        'hooks' => [],
    ]);

    expect($config->getHooks('after_clone'))->toBeArray();
    expect($config->getHooks('after_clone'))->toBeEmpty();
});

test('multiline deployment hooks are split correctly', function () {
    $config = DeploymentConfig::fromArray([
        'server' => ['host' => 'test.com', 'username' => 'deployer'],
        'repository' => ['url' => 'git@github.com:test/repo.git'],
        'paths' => ['deploy_to' => '/var/www/app'],
        'hooks' => [
            'after_clone' => [
                'composer install --no-dev',
                'npm ci --production',
                'cp .env.example .env',
            ],
        ],
    ]);

    $hooks = $config->getHooks('after_clone');
    expect($hooks)->toBeArray();
    expect($hooks)->toHaveCount(3);
    expect($hooks[0])->toBe('composer install --no-dev');
    expect($hooks[1])->toBe('npm ci --production');
    expect($hooks[2])->toBe('cp .env.example .env');
});

test('default hooks are provided when env var not set', function () {
    // Ensure environment is clean
    putenv('DEPLOY_HOOKS_AFTER_CLONE');
    putenv('DEPLOY_HOST=test.example.com');
    putenv('DEPLOY_USERNAME=testuser');
    putenv('DEPLOY_REPO_URL=git@github.com:test/repo.git');
    putenv('DEPLOY_PATH=/var/www/test-app');

    $config = require __DIR__.'/../../config/deploy.php';

    // Should have default composer install
    expect($config['hooks']['after_clone'])->toContain('composer install --no-dev --optimize-autoloader');

    // Clean up
    putenv('DEPLOY_HOST');
    putenv('DEPLOY_USERNAME');
    putenv('DEPLOY_REPO_URL');
    putenv('DEPLOY_PATH');
});
