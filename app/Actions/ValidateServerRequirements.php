<?php

declare(strict_types=1);

namespace App\Actions;

use App\Exceptions\ValidationException;
use App\Services\RemoteExecutor;
use App\ValueObjects\DeploymentConfig;
use App\ValueObjects\ValidationResult;
use Psr\Log\LoggerInterface;

final readonly class ValidateServerRequirements
{
    public function __construct(
        private RemoteExecutor $executor,
        private LoggerInterface $logger,
    ) {}

    public function handle(DeploymentConfig $config): ValidationResult
    {
        $this->logger->info('Validating server requirements...');

        $checks = [
            'disk_space' => $this->checkDiskSpace($config->getDeployPath()),
            'permissions' => $this->checkPermissions($config->getDeployPath()),
            'php_version' => $this->checkPhpVersion(),
            'git' => $this->checkGitAvailable(),
            'composer' => $this->checkComposerAvailable($config),
        ];

        $failures = array_filter($checks, fn (bool $check): bool => ! $check);

        if (count($failures) > 0) {
            $failedChecks = implode(', ', array_keys($failures));
            throw new ValidationException("Server requirements not met: {$failedChecks}");
        }

        $this->logger->info('All server requirements validated');

        return new ValidationResult(
            passed: true,
            checks: $checks,
        );
    }

    private function checkDiskSpace(string $path): bool
    {
        $result = $this->executor->execute(
            "df -BM {$path} | awk 'NR==2 {print \$4}'",
            throwOnError: false
        );

        if ($result->isFailed()) {
            return false;
        }

        $availableMB = (int) str_replace('M', '', mb_trim($result->output));

        $this->logger->debug("Available disk space: {$availableMB}MB");

        return $availableMB >= 500;
    }

    private function checkPermissions(string $path): bool
    {
        $parentPath = dirname($path);

        $result = $this->executor->execute(
            "test -d {$parentPath} && test -w {$parentPath}",
            throwOnError: false
        );

        return $result->isSuccessful();
    }

    private function checkPhpVersion(): bool
    {
        $result = $this->executor->execute(
            "php -r 'echo PHP_VERSION;'",
            throwOnError: false
        );

        if ($result->isFailed()) {
            return false;
        }

        $version = mb_trim($result->output);
        $this->logger->debug("PHP version: {$version}");

        return version_compare($version, '8.3.0', '>=');
    }

    private function checkGitAvailable(): bool
    {
        $result = $this->executor->execute('which git', throwOnError: false);

        return $result->isSuccessful();
    }

    private function checkComposerAvailable(DeploymentConfig $config): bool
    {
        if (! $config->shouldUseComposer()) {
            return true; // Skip check if not needed
        }

        $result = $this->executor->execute('which composer', throwOnError: false);

        return $result->isSuccessful();
    }
}
