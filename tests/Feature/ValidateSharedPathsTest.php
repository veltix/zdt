<?php

declare(strict_types=1);

use App\Actions\ValidateDeploymentConfig;

beforeEach(function () {
    // Clean up environment variables before each test
    putenv('DEPLOY_HOST');
    putenv('DEPLOY_USERNAME');
    putenv('DEPLOY_REPO_URL');
    putenv('DEPLOY_PATH');
    putenv('DEPLOY_SHARED_PATHS');
});

test('parses shared paths from environment variables', function () {
    putenv('DEPLOY_HOST=test.com');
    putenv('DEPLOY_USERNAME=testuser');
    putenv('DEPLOY_REPO_URL=https://github.com/test/repo.git');

    // Test: storage, resources/lang:lang, public/uploads:uploads
    putenv('DEPLOY_SHARED_PATHS=storage, resources/lang:lang, public/uploads:uploads');

    $validator = new ValidateDeploymentConfig;
    $config = $validator->handle('deploy.php');

    $sharedPaths = $config->getSharedPaths();

    // Verify count
    expect($sharedPaths)->toHaveCount(3);

    // Verify mappings
    expect($sharedPaths['storage'])->toBe('storage');
    expect($sharedPaths['resources/lang'])->toBe('lang');
    expect($sharedPaths['public/uploads'])->toBe('uploads');
});

test('parses shared paths handles empty or invalid input', function () {
    putenv('DEPLOY_HOST=test.com');
    putenv('DEPLOY_USERNAME=testuser');
    putenv('DEPLOY_REPO_URL=https://github.com/test/repo.git');

    // Test empty string should result in empty array
    putenv('DEPLOY_SHARED_PATHS=');
    $validator = new ValidateDeploymentConfig;
    $config = $validator->handle('deploy.php');
    expect($config->getSharedPaths())->toBeEmpty();

    // Test string with only commas/spaces
    putenv('DEPLOY_SHARED_PATHS= , , ');
    $config = $validator->handle('deploy.php');
    expect($config->getSharedPaths())->toBeEmpty();
});
