<?php

declare(strict_types=1);

use App\Exceptions\ValidationException;
use App\ValueObjects\DeploymentConfig;

test('deployment config can be created from valid array', function () {
    $config = DeploymentConfig::fromArray(require __DIR__.'/../../fixtures/deploy-config-valid.php');

    expect($config->server['host'])->toBe('test.example.com');
    expect($config->repository['url'])->toBe('git@github.com:test/repo.git');
    expect($config->getDeployPath())->toBe('/var/www/test-app');
});

test('deployment config validation fails when host is missing', function () {
    $invalidConfig = require __DIR__.'/../../fixtures/deploy-config-invalid.php';

    DeploymentConfig::fromArray($invalidConfig);
})->throws(ValidationException::class);

test('deployment config provides server credentials', function () {
    $config = DeploymentConfig::fromArray(require __DIR__.'/../../fixtures/deploy-config-valid.php');

    $credentials = $config->getServerCredentials();

    expect($credentials->host)->toBe('test.example.com');
    expect($credentials->username)->toBe('testuser');
    expect($credentials->port)->toBe(22);
});

test('deployment config provides repository settings', function () {
    $config = DeploymentConfig::fromArray(require __DIR__.'/../../fixtures/deploy-config-valid.php');

    expect($config->getRepositoryUrl())->toBe('git@github.com:test/repo.git');
    expect($config->getBranch())->toBe('main');
});

test('deployment config provides hooks', function () {
    $config = DeploymentConfig::fromArray(require __DIR__.'/../../fixtures/deploy-config-valid.php');

    $afterCloneHooks = $config->getHooks('after_clone');

    expect($afterCloneHooks)->toBeArray();
    expect($afterCloneHooks)->toContain('composer install');
});

test('deployment config provides health check settings', function () {
    $config = DeploymentConfig::fromArray(require __DIR__.'/../../fixtures/deploy-config-valid.php');

    expect($config->isHealthCheckEnabled())->toBeTrue();
    expect($config->getHealthCheckUrl())->toBe('https://test.example.com/health');
    expect($config->getHealthCheckTimeout())->toBe(30);
});

test('deployment config parses hooks from environment variables', function () {
    // Set environment variables
    putenv("DEPLOY_HOOKS_BEFORE_CLONE=echo 'Starting deployment'\nls -la");
    putenv('DEPLOY_HOOKS_AFTER_CLONE=composer install --no-dev');
    putenv("DEPLOY_HOOKS_BEFORE_ACTIVATE=php artisan migrate\nphp artisan cache:clear\nphp artisan config:cache");
    putenv('DEPLOY_HOST=test.example.com');
    putenv('DEPLOY_USERNAME=testuser');
    putenv('DEPLOY_REPO_URL=git@github.com:test/repo.git');
    putenv('DEPLOY_PATH=/var/www/test-app');

    // Load config
    $config = require __DIR__.'/../../../config/deploy.php';

    expect($config['hooks']['before_clone'])->toBeArray();
    expect($config['hooks']['before_clone'])->toHaveCount(2);
    expect($config['hooks']['before_clone'][0])->toBe("echo 'Starting deployment'");
    expect($config['hooks']['before_clone'][1])->toBe('ls -la');

    expect($config['hooks']['after_clone'])->toBeArray();
    expect($config['hooks']['after_clone'])->toContain('composer install --no-dev');

    expect($config['hooks']['before_activate'])->toBeArray();
    expect($config['hooks']['before_activate'])->toHaveCount(3);
    expect($config['hooks']['before_activate'][0])->toBe('php artisan migrate');
    expect($config['hooks']['before_activate'][1])->toBe('php artisan cache:clear');
    expect($config['hooks']['before_activate'][2])->toBe('php artisan config:cache');

    // Clean up
    putenv('DEPLOY_HOOKS_BEFORE_CLONE');
    putenv('DEPLOY_HOOKS_AFTER_CLONE');
    putenv('DEPLOY_HOOKS_BEFORE_ACTIVATE');
    putenv('DEPLOY_HOST');
    putenv('DEPLOY_USERNAME');
    putenv('DEPLOY_REPO_URL');
    putenv('DEPLOY_PATH');
});

test('deployment config validation fails when host is empty', function () {
    $config = [
        'server' => [
            'host' => '',
            'username' => 'testuser',
        ],
        'repository' => [
            'url' => 'git@github.com:test/repo.git',
        ],
        'paths' => [
            'deploy_to' => '/var/www/app',
        ],
        'options' => [],
        'hooks' => [],
        'health_check' => [],
    ];

    DeploymentConfig::fromArray($config);
})->throws(ValidationException::class, 'Server host is required');

test('deployment config validation fails when username is missing', function () {
    $config = [
        'server' => [
            'host' => 'test.example.com',
            'username' => '',
        ],
        'repository' => [
            'url' => 'git@github.com:test/repo.git',
        ],
        'paths' => [
            'deploy_to' => '/var/www/app',
        ],
        'options' => [],
        'hooks' => [],
        'health_check' => [],
    ];

    DeploymentConfig::fromArray($config);
})->throws(ValidationException::class, 'Server username is required');

test('deployment config validation fails when repository url is missing', function () {
    $config = [
        'server' => [
            'host' => 'test.example.com',
            'username' => 'testuser',
        ],
        'repository' => [
            'url' => '',
        ],
        'paths' => [
            'deploy_to' => '/var/www/app',
        ],
        'options' => [],
        'hooks' => [],
        'health_check' => [],
    ];

    DeploymentConfig::fromArray($config);
})->throws(ValidationException::class, 'Repository URL is required');

test('deployment config validation fails when deploy path is missing', function () {
    $config = [
        'server' => [
            'host' => 'test.example.com',
            'username' => 'testuser',
        ],
        'repository' => [
            'url' => 'git@github.com:test/repo.git',
        ],
        'paths' => [
            'deploy_to' => '',
        ],
        'options' => [],
        'hooks' => [],
        'health_check' => [],
    ];

    DeploymentConfig::fromArray($config);
})->throws(ValidationException::class, 'Deployment path is required');

test('getServerCredentials expands tilde in key path', function () {
    $_SERVER['HOME'] = '/home/testuser';

    $config = [
        'server' => [
            'host' => 'test.example.com',
            'username' => 'testuser',
            'key_path' => '~/.ssh/id_rsa',
        ],
        'repository' => [
            'url' => 'git@github.com:test/repo.git',
        ],
        'paths' => [
            'deploy_to' => '/var/www/app',
        ],
        'options' => [],
        'hooks' => [],
        'health_check' => [],
    ];

    $deploymentConfig = DeploymentConfig::fromArray($config);
    $credentials = $deploymentConfig->getServerCredentials();

    expect($credentials->keyPath)->toBe('/home/testuser/.ssh/id_rsa');
});
