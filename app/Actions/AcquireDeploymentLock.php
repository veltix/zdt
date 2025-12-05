<?php

declare(strict_types=1);

namespace App\Actions;

use App\Exceptions\DeploymentLockedException;
use App\Services\RemoteExecutor;
use App\ValueObjects\DeploymentConfig;
use Psr\Log\LoggerInterface;

final readonly class AcquireDeploymentLock
{
    private const LOCK_TIMEOUT_SECONDS = 3600;

    public function __construct(
        private RemoteExecutor $executor,
        private LoggerInterface $logger,
    ) {}

    public function handle(DeploymentConfig $config): void
    {
        $lockFile = $this->getLockPath($config);

        if ($this->lockExists($lockFile)) {
            $this->handleExistingLock($lockFile);
        }

        $this->createLock($lockFile);

        $this->logger->info("Deployment lock acquired: {$lockFile}");
    }

    private function lockExists(string $lockFile): bool
    {
        $result = $this->executor->execute("test -f {$lockFile}", throwOnError: false);

        return $result->isSuccessful();
    }

    private function handleExistingLock(string $lockFile): void
    {
        $ageResult = $this->executor->execute(
            "stat -c %Y {$lockFile} 2>/dev/null || stat -f %m {$lockFile}",
            throwOnError: false
        );

        if ($ageResult->isSuccessful()) {
            $lockTime = (int) mb_trim($ageResult->output);
            $currentTime = time();
            $age = $currentTime - $lockTime;

            if ($age > self::LOCK_TIMEOUT_SECONDS) {
                $this->logger->warning("Removing stale deployment lock (age: {$age}s)");
                $this->executor->execute("rm -f {$lockFile}");

                return;
            }

            $remainingTime = self::LOCK_TIMEOUT_SECONDS - $age;

            throw new DeploymentLockedException(
                "Another deployment is in progress. Lock file: {$lockFile} (age: {$age}s, timeout in {$remainingTime}s)"
            );
        }

        throw new DeploymentLockedException(
            "Another deployment is in progress. Lock file: {$lockFile}"
        );
    }

    private function createLock(string $lockFile): void
    {
        $hostname = gethostname() ?: 'unknown';
        $pid = getmypid() ?: 'unknown';
        $timestamp = date('Y-m-d H:i:s');

        $lockContent = json_encode([
            'hostname' => $hostname,
            'pid' => $pid,
            'timestamp' => $timestamp,
        ], JSON_PRETTY_PRINT);

        $this->executor->execute("echo '{$lockContent}' > {$lockFile}");
    }

    private function getLockPath(DeploymentConfig $config): string
    {
        return $config->getDeployPath().'/.deploy.lock';
    }
}
