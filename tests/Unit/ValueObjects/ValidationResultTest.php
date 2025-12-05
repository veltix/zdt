<?php

declare(strict_types=1);

use App\ValueObjects\ValidationResult;

test('can be created with passing validation', function () {
    $result = new ValidationResult(
        passed: true,
    );

    expect($result->passed)->toBeTrue()
        ->and($result->checks)->toBe([])
        ->and($result->message)->toBeNull();
});

test('can be created with failing validation', function () {
    $result = new ValidationResult(
        passed: false,
        message: 'Validation failed',
    );

    expect($result->passed)->toBeFalse()
        ->and($result->checks)->toBe([])
        ->and($result->message)->toBe('Validation failed');
});

test('can include validation checks', function () {
    $checks = [
        'php_version' => '8.3.0',
        'disk_space' => '500MB',
        'git_installed' => true,
    ];

    $result = new ValidationResult(
        passed: true,
        checks: $checks,
    );

    expect($result->passed)->toBeTrue()
        ->and($result->checks)->toBe($checks)
        ->and($result->checks['php_version'])->toBe('8.3.0')
        ->and($result->checks['disk_space'])->toBe('500MB')
        ->and($result->checks['git_installed'])->toBeTrue();
});

test('can include checks with failing validation', function () {
    $checks = [
        'php_version' => '7.4.0',
        'disk_space' => '10MB',
    ];

    $result = new ValidationResult(
        passed: false,
        checks: $checks,
        message: 'PHP version too low and insufficient disk space',
    );

    expect($result->passed)->toBeFalse()
        ->and($result->checks)->toBe($checks)
        ->and($result->message)->toBe('PHP version too low and insufficient disk space');
});

test('isFailed returns true when passed is false', function () {
    $result = new ValidationResult(
        passed: false,
    );

    expect($result->isFailed())->toBeTrue();
});

test('isFailed returns false when passed is true', function () {
    $result = new ValidationResult(
        passed: true,
    );

    expect($result->isFailed())->toBeFalse();
});
