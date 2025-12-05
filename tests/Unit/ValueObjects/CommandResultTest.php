<?php

declare(strict_types=1);

use App\ValueObjects\CommandResult;

test('command result indicates success when exit code is zero', function () {
    $result = new CommandResult(
        exitCode: 0,
        output: 'Success output',
        command: 'ls -la'
    );

    expect($result->isSuccessful())->toBeTrue();
    expect($result->isFailed())->toBeFalse();
});

test('command result indicates failure when exit code is non-zero', function () {
    $result = new CommandResult(
        exitCode: 1,
        output: 'Error output',
        command: 'invalid-command'
    );

    expect($result->isSuccessful())->toBeFalse();
    expect($result->isFailed())->toBeTrue();
});

test('command result stores all properties correctly', function () {
    $result = new CommandResult(
        exitCode: 2,
        output: 'Command output',
        command: 'git clone'
    );

    expect($result->exitCode)->toBe(2);
    expect($result->output)->toBe('Command output');
    expect($result->command)->toBe('git clone');
});
