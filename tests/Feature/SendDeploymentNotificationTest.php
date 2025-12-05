<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\SendDeploymentNotification;
use App\ValueObjects\DeploymentConfig;
use App\ValueObjects\Release;
use DateTimeImmutable;
use Exception;
use Illuminate\Support\Facades\Http;
use Mockery;
use Psr\Log\LoggerInterface;

beforeEach(function () {
    $this->logger = Mockery::mock(LoggerInterface::class);
    $this->logger->shouldIgnoreMissing();

    $this->action = new SendDeploymentNotification($this->logger);

    $this->release = new Release(
        name: '20250101-120000',
        path: '/var/www/app/releases/20250101-120000',
        createdAt: new DateTimeImmutable,
    );
});

test('notification skips when webhook URL not configured', function () {
    Http::fake();
    $config = new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'repo', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: [],
        hooks: [],
        healthCheck: [],
        sharedPaths: [],
        database: [],
        notifications: [], // no webhook
    );

    $this->action->handle($config, $this->release, 'success');

    Http::assertNothingSent();
});

test('notification sends started status', function () {
    Http::fake();
    $url = 'https://webhook.test/started';
    $config = createConfigWithWebhook($url);

    $this->action->handle($config, $this->release, 'started');

    Http::assertSent(function ($request) use ($url) {
        return $request->url() === $url &&
               str_contains($request['text'], '🚀') &&
               str_contains($request['text'], 'Deployment started');
    });
});

test('notification sends success status', function () {
    Http::fake();
    $url = 'https://webhook.test/success';
    $config = createConfigWithWebhook($url);

    $this->action->handle($config, $this->release, 'success');

    Http::assertSent(function ($request) use ($url) {
        return $request->url() === $url &&
               str_contains($request['text'], '✅') &&
               str_contains($request['text'], 'Deployment successful');
    });
});

test('notification sends failed status with error', function () {
    Http::fake();
    $url = 'https://webhook.test/failed';
    $config = createConfigWithWebhook($url);

    $this->action->handle($config, $this->release, 'failed', 'Something went wrong');

    Http::assertSent(function ($request) use ($url) {
        return $request->url() === $url &&
               str_contains($request['text'], '❌') &&
               str_contains($request['text'], 'Deployment failed') &&
               str_contains($request['text'], 'Error: Something went wrong') &&
               str_contains($request['blocks'][0]['text']['text'], '*Error:* Something went wrong');
    });
});

test('notification sends rolled_back status', function () {
    Http::fake();
    $url = 'https://webhook.test/rollback';
    $config = createConfigWithWebhook($url);

    $this->action->handle($config, $this->release, 'rolled_back');

    Http::assertSent(function ($request) use ($url) {
        return $request->url() === $url &&
               str_contains($request['text'], '⏪') &&
               str_contains($request['text'], 'Deployment rolled back');
    });
});

test('notification sends default status', function () {
    Http::fake();
    $url = 'https://webhook.test/other';
    $config = createConfigWithWebhook($url);

    $this->action->handle($config, $this->release, 'custom');

    Http::assertSent(function ($request) use ($url) {
        return $request->url() === $url &&
               str_contains($request['text'], 'ℹ️') &&
               str_contains($request['text'], 'Deployment custom');
    });
});

test('notification logs warning on http failure', function () {
    $url = 'https://webhook.test/fail';
    $config = createConfigWithWebhook($url);

    Http::fake([
        $url => Http::response('Server Error', 500),
    ]);

    $this->logger->shouldReceive('warning')
        ->with(Mockery::pattern('/Failed to send deployment notification.*Status 500/'))
        ->once();

    $this->action->handle($config, $this->release, 'success');
});

test('notification logs warning on exception', function () {
    $url = 'https://webhook.test/exception';
    $config = createConfigWithWebhook($url);

    // Use Facade partial mock to simulate exception
    Http::shouldReceive('timeout')
        ->with(5)
        ->andReturnSelf();

    Http::shouldReceive('post')
        ->with($url, Mockery::any())
        ->andThrow(new Exception('Connection failed'));

    $this->logger->shouldReceive('warning')
        ->with(Mockery::pattern('/Failed to send deployment notification.*Connection failed/'))
        ->once();

    $this->action->handle($config, $this->release, 'success');
});

function createConfigWithWebhook(string $url): DeploymentConfig
{
    return new DeploymentConfig(
        server: ['host' => 'test.com', 'username' => 'user'],
        repository: ['url' => 'repo', 'branch' => 'main'],
        paths: ['deploy_to' => '/var/www/app'],
        options: [],
        hooks: [],
        healthCheck: [],
        sharedPaths: [],
        database: [],
        notifications: ['webhook_url' => $url],
    );
}
