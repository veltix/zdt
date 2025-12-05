<?php

declare(strict_types=1);

namespace App\Actions;

use App\ValueObjects\DeploymentConfig;
use App\ValueObjects\Release;
use Exception;
use Psr\Log\LoggerInterface;

final readonly class SendDeploymentNotification
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function handle(DeploymentConfig $config, Release $release, string $status, ?string $error = null): void
    {
        $webhookUrl = $config->getNotificationWebhook();

        if (! $webhookUrl) {
            return;
        }

        $payload = $this->buildPayload($config, $release, $status, $error);

        $this->sendWebhook($webhookUrl, $payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(DeploymentConfig $config, Release $release, string $status, ?string $error): array
    {
        $emoji = match ($status) {
            'started' => '🚀',
            'success' => '✅',
            'failed' => '❌',
            'rolled_back' => '⏪',
            default => 'ℹ️',
        };

        $message = match ($status) {
            'started' => "Deployment started: {$release->name}",
            'success' => "Deployment successful: {$release->name}",
            'failed' => "Deployment failed: {$release->name}",
            'rolled_back' => "Deployment rolled back: {$release->name}",
            default => "Deployment {$status}: {$release->name}",
        };

        if ($error) {
            $message .= "\nError: {$error}";
        }

        return [
            'text' => "{$emoji} {$message}",
            'blocks' => [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "*{$emoji} Deployment {$status}*\n"
                            ."*Release:* {$release->name}\n"
                            ."*Server:* {$config->server['host']}\n"
                            ."*Branch:* {$config->getBranch()}".
                            ($error ? "\n*Error:* {$error}" : ''),
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendWebhook(string $url, array $payload): void
    {
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(5)
                ->post($url, $payload);

            if ($response->successful()) {
                $this->logger->debug("Deployment notification sent to {$url}");
            } else {
                $this->logger->warning("Failed to send deployment notification to {$url}: Status {$response->status()}");
            }
        } catch (Exception $e) {
            $this->logger->warning("Failed to send deployment notification to {$url}: {$e->getMessage()}");
        }
    }
}
