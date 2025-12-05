<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\PerformHealthCheck;
use App\Exceptions\HealthCheckFailedException;
use App\ValueObjects\DeploymentConfig;
use App\ValueObjects\Release;
use DateTimeImmutable;
use Illuminate\Support\Facades\Http;
use Mockery;
use Psr\Log\LoggerInterface;

beforeEach(function () {
    $this->logger = Mockery::mock(LoggerInterface::class);
    $this->logger->shouldReceive('info', 'warning', 'error', 'debug')->byDefault();

    $this->action = new PerformHealthCheck($this->logger);

    $this->release = new Release(
        name: '20230101000000',
        path: '/var/www/app/releases/20230101000000',
        createdAt: new DateTimeImmutable()
    );
});

test('it skips health check when disabled', function () {
    Http::fake(); // Prevent real requests

    $config = new DeploymentConfig(
        server: ['host' => 'localhost', 'port' => 22, 'username' => 'user', 'key_path' => '/key'],
        repository: ['url' => 'repo', 'branch' => 'main'],
        paths: ['deploy_to' => '/path'],
        options: [],
        hooks: [],
        healthCheck: ['enabled' => false]
    );

    $result = $this->action->handle($config, $this->release);

    expect($result->healthy)->toBeTrue();
    expect($result->message)->toBe('Health check disabled');
    Http::assertNothingSent();
});

test('it skips health check when url is missing', function () {
    Http::fake(); // Prevent real requests

    $config = new DeploymentConfig(
        server: ['host' => 'localhost', 'port' => 22, 'username' => 'user', 'key_path' => '/key'],
        repository: ['url' => 'repo', 'branch' => 'main'],
        paths: ['deploy_to' => '/path'],
        options: [],
        hooks: [],
        healthCheck: ['enabled' => true, 'url' => '']
    );

    $result = $this->action->handle($config, $this->release);

    expect($result->healthy)->toBeTrue();
    expect($result->message)->toBe('No health check URL configured');
    Http::assertNothingSent();
});

test('it passes when health check returns 200 OK', function () {
    $config = new DeploymentConfig(
        server: ['host' => 'localhost', 'port' => 22, 'username' => 'user', 'key_path' => '/key'],
        repository: ['url' => 'repo', 'branch' => 'main'],
        paths: ['deploy_to' => '/path'],
        options: [],
        hooks: [],
        healthCheck: ['enabled' => true, 'url' => 'http://example.com', 'timeout' => 30]
    );

    Http::fake([
        'http://example.com' => Http::response('OK', 200),
    ]);

    $result = $this->action->handle($config, $this->release);

    expect($result->healthy)->toBeTrue();
    expect($result->message)->toContain('Health check passed (200)');

    Http::assertSent(function ($request) {
        return $request->url() === 'http://example.com' &&
               $request->hasHeader('User-Agent', 'ZDT-Health-Check');
    });
});

test('it throws exception when status code is error', function () {
    $config = new DeploymentConfig(
        server: ['host' => 'localhost', 'port' => 22, 'username' => 'user', 'key_path' => '/key'],
        repository: ['url' => 'repo', 'branch' => 'main'],
        paths: ['deploy_to' => '/path'],
        options: [],
        hooks: [],
        healthCheck: ['enabled' => true, 'url' => 'http://example.com', 'timeout' => 30]
    );

    Http::fake([
        '*' => Http::response('Error', 500),
    ]);

    expect(fn () => $this->action->handle($config, $this->release))
        ->toThrow(HealthCheckFailedException::class, 'returned status code 500');
});
