<?php

declare(strict_types=1);

use App\ValueObjects\Release;

test('activate sets isActive to true', function () {
    $release = Release::create('/var/www/app');

    expect($release->isActive)->toBeFalse();

    $activatedRelease = $release->activate();

    expect($activatedRelease->isActive)->toBeTrue();
    expect($activatedRelease->name)->toBe($release->name);
    expect($activatedRelease->path)->toBe($release->path);
    expect($activatedRelease->createdAt)->toBe($release->createdAt);
});

test('activate preserves commit hash and branch', function () {
    $release = Release::create('/var/www/app')
        ->withCommitHash('abc123')
        ->withBranch('main');

    $activatedRelease = $release->activate();

    expect($activatedRelease->isActive)->toBeTrue();
    expect($activatedRelease->commitHash)->toBe('abc123');
    expect($activatedRelease->branch)->toBe('main');
});
