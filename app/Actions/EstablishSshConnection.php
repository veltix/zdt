<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\SshConnectionContract;
use App\Exceptions\SshConnectionException;
use App\ValueObjects\DeploymentConfig;
use Psr\Log\LoggerInterface;

final readonly class EstablishSshConnection
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function handle(SshConnectionContract $ssh, DeploymentConfig $config): void
    {
        $maxAttempts = 3;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $attempt++;

            try {
                $ssh->connect();
                $this->logger->info("Connected to {$config->server['host']}");

                return;
            } catch (SshConnectionException $e) {
                if ($attempt < $maxAttempts) {
                    $delay = 2 ** ($attempt - 1);
                    $this->logger->warning(
                        "Connection attempt {$attempt} failed, retrying in {$delay} seconds..."
                    );
                    sleep($delay);
                } else {
                    throw $e;
                }
            }
        }
    }
}
