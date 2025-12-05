<?php

declare(strict_types=1);

use App\Exceptions\ValidationException;
use App\ValueObjects\ServerCredentials;

test('server credentials can be created with valid data', function () {
    $credentials = new ServerCredentials(
        host: 'example.com',
        port: 22,
        username: 'deployer',
        keyPath: '/path/to/key',
        timeout: 300
    );

    expect($credentials->host)->toBe('example.com');
    expect($credentials->port)->toBe(22);
    expect($credentials->username)->toBe('deployer');
    expect($credentials->keyPath)->toBe('/path/to/key');
    expect($credentials->timeout)->toBe(300);
});

test('server credentials expands tilde in key path', function () {
    $credentials = new ServerCredentials(
        host: 'example.com',
        port: 22,
        username: 'deployer',
        keyPath: '~/.ssh/id_rsa',
        timeout: 300
    );

    // Path should be expanded to actual home directory
    expect($credentials->keyPath)->not->toBe('~/.ssh/id_rsa');
    expect($credentials->keyPath)->toContain('.ssh/id_rsa');
});

test('server credentials validation fails when host is empty', function () {
    new ServerCredentials(
        host: '',
        port: 22,
        username: 'deployer',
        keyPath: '~/.ssh/id_rsa',
        timeout: 300
    );
})->throws(ValidationException::class, 'Server host is required');

test('server credentials validation fails when port is invalid', function () {
    new ServerCredentials(
        host: 'example.com',
        port: 70000,
        username: 'deployer',
        keyPath: '~/.ssh/id_rsa',
        timeout: 300
    );
})->throws(ValidationException::class, 'Server port must be between 1 and 65535');

test('server credentials validation fails when username is empty', function () {
    new ServerCredentials(
        host: 'example.com',
        port: 22,
        username: '',
        keyPath: '~/.ssh/id_rsa',
        timeout: 300
    );
})->throws(ValidationException::class, 'Server username is required');

test('server credentials validation fails when timeout is too short', function () {
    new ServerCredentials(
        host: 'example.com',
        port: 22,
        username: 'deployer',
        keyPath: '~/.ssh/id_rsa',
        timeout: 0
    );
})->throws(ValidationException::class, 'Timeout must be at least 1 second');

test('server credentials validation fails when key path is empty', function () {
    new ServerCredentials(
        host: 'example.com',
        port: 22,
        username: 'deployer',
        keyPath: '',
        timeout: 300
    );
})->throws(ValidationException::class, 'SSH key path is required');
