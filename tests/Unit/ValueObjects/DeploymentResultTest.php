<?php

declare(strict_types=1);

use App\ValueObjects\DeploymentResult;
use App\ValueObjects\Release;

test('can be created with successful deployment', function () {
    $release = new Release(
        name: '20231201120000',
        path: '/var/www/releases/20231201120000',
        createdAt: new DateTimeImmutable('2023-12-01 12:00:00'),
    );

    $result = new DeploymentResult(
        success: true,
        release: $release,
    );

    expect($result->success)->toBeTrue()
        ->and($result->release)->toBe($release)
        ->and($result->message)->toBeNull()
        ->and($result->previousRelease)->toBeNull();
});

test('can be created with failed deployment', function () {
    $release = new Release(
        name: '20231201120000',
        path: '/var/www/releases/20231201120000',
        createdAt: new DateTimeImmutable('2023-12-01 12:00:00'),
    );

    $result = new DeploymentResult(
        success: false,
        release: $release,
        message: 'Deployment failed due to health check',
    );

    expect($result->success)->toBeFalse()
        ->and($result->release)->toBe($release)
        ->and($result->message)->toBe('Deployment failed due to health check')
        ->and($result->previousRelease)->toBeNull();
});

test('can include previous release', function () {
    $currentRelease = new Release(
        name: '20231201120000',
        path: '/var/www/releases/20231201120000',
        createdAt: new DateTimeImmutable('2023-12-01 12:00:00'),
    );

    $previousRelease = new Release(
        name: '20231201110000',
        path: '/var/www/releases/20231201110000',
        createdAt: new DateTimeImmutable('2023-12-01 11:00:00'),
    );

    $result = new DeploymentResult(
        success: true,
        release: $currentRelease,
        previousRelease: $previousRelease,
    );

    expect($result->success)->toBeTrue()
        ->and($result->release)->toBe($currentRelease)
        ->and($result->previousRelease)->toBe($previousRelease);
});

test('isFailed returns true when success is false', function () {
    $release = new Release(
        name: '20231201120000',
        path: '/var/www/releases/20231201120000',
        createdAt: new DateTimeImmutable('2023-12-01 12:00:00'),
    );

    $result = new DeploymentResult(
        success: false,
        release: $release,
    );

    expect($result->isFailed())->toBeTrue();
});

test('isFailed returns false when success is true', function () {
    $release = new Release(
        name: '20231201120000',
        path: '/var/www/releases/20231201120000',
        createdAt: new DateTimeImmutable('2023-12-01 12:00:00'),
    );

    $result = new DeploymentResult(
        success: true,
        release: $release,
    );

    expect($result->isFailed())->toBeFalse();
});
