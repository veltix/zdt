<?php

declare(strict_types=1);

namespace App\Actions;

use App\Exceptions\HealthCheckFailedException;
use App\ValueObjects\DeploymentConfig;
use App\ValueObjects\HealthCheckResult;
use App\ValueObjects\Release;
use Exception;
use Psr\Log\LoggerInterface;

final readonly class PerformHealthCheck
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function handle(DeploymentConfig $config, Release $release): HealthCheckResult
    {
        if (! $config->isHealthCheckEnabled()) {
            $this->logger->info('Health check disabled, skipping');

            return new HealthCheckResult(healthy: true, message: 'Health check disabled');
        }

        $url = $config->getHealthCheckUrl();
        if (! $url) {
            $this->logger->warning('Health check enabled but no URL configured, skipping');

            return new HealthCheckResult(healthy: true, message: 'No health check URL configured');
        }

        $timeout = $config->getHealthCheckTimeout();

        $this->logger->info("Performing health check: {$url}");

        $startTime = microtime(true);

        try {
            $response = \Illuminate\Support\Facades\Http::timeout($timeout)
                ->withUserAgent('ZDT-Health-Check')
                ->get($url);

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            if ($response->successful()) {
                $statusCode = $response->status();
                $this->logger->info("Health check passed: {$url} returned {$statusCode} in {$duration}ms");

                return new HealthCheckResult(
                    healthy: true,
                    message: "Health check passed ({$statusCode}) in {$duration}ms"
                );
            }

            $statusCode = $response->status();
            $message = "Health check failed: {$url} returned status code {$statusCode}";
            $this->logger->error($message);

            throw new HealthCheckFailedException($message);
        } catch (Exception $e) {
            // Handle connection errors (Guzzle exceptions)
            $message = "Health check failed: {$url} - ".$e->getMessage();
            $this->logger->error($message);

            throw new HealthCheckFailedException($message);
        }
    }
}
