<?php

declare(strict_types=1);

use App\Actions\PerformHealthCheck;
use App\ValueObjects\DeploymentConfig;
use App\ValueObjects\Release;
use Psr\Log\NullLogger;

beforeEach(function () {
    $this->logger = new NullLogger;
    $this->action = new PerformHealthCheck($this->logger);

    $this->config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: [],
        hooks: [],
        healthCheck: ['enabled' => true, 'url' => 'http://example.com/health', 'timeout' => 30],
        sharedPaths: [],
        database: [],
        notifications: [],
    );

    $this->release = new Release('20231215-143000', '/var/www/app/releases/20231215-143000', new DateTimeImmutable);
});

test('health check returns success when disabled', function () {
    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: [],
        hooks: [],
        healthCheck: ['enabled' => false],
        sharedPaths: [],
        database: [],
        notifications: [],
    );

    $result = $this->action->handle($config, $this->release);

    expect($result->healthy)->toBeTrue();
    expect($result->message)->toContain('disabled');
});

test('health check returns success when no URL configured', function () {
    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: [],
        hooks: [],
        healthCheck: ['enabled' => true, 'url' => null],
        sharedPaths: [],
        database: [],
        notifications: [],
    );

    $result = $this->action->handle($config, $this->release);

    expect($result->healthy)->toBeTrue();
    expect($result->message)->toContain('No health check URL');
});

test('health check validates enabled status correctly', function () {
    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: [],
        hooks: [],
        healthCheck: ['enabled' => true],
        sharedPaths: [],
        database: [],
        notifications: [],
    );

    expect($config->isHealthCheckEnabled())->toBeTrue();
});

test('health check validates URL configuration correctly', function () {
    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: [],
        hooks: [],
        healthCheck: ['enabled' => true, 'url' => 'https://example.com/health'],
        sharedPaths: [],
        database: [],
        notifications: [],
    );

    expect($config->getHealthCheckUrl())->toBe('https://example.com/health');
});

test('health check respects timeout configuration', function () {
    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: [],
        hooks: [],
        healthCheck: ['enabled' => true, 'timeout' => 60],
        sharedPaths: [],
        database: [],
        notifications: [],
    );

    expect($config->getHealthCheckTimeout())->toBe(60);
});

test('health check uses default timeout when not specified', function () {
    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'https://github.com/test/repo.git', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: [],
        hooks: [],
        healthCheck: ['enabled' => true],
        sharedPaths: [],
        database: [],
        notifications: [],
    );

    expect($config->getHealthCheckTimeout())->toBe(30);
});

test('health check result isUnhealthy returns false when healthy', function () {
    $result = new App\ValueObjects\HealthCheckResult(healthy: true, message: 'All good');

    expect($result->isUnhealthy())->toBeFalse();
});

test('health check result isUnhealthy returns true when unhealthy', function () {
    $result = new App\ValueObjects\HealthCheckResult(healthy: false, message: 'Something went wrong');

    expect($result->isUnhealthy())->toBeTrue();
});
