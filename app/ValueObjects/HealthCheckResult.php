<?php

declare(strict_types=1);

namespace App\ValueObjects;

final readonly class HealthCheckResult
{
    public function __construct(
        public bool $healthy,
        public ?string $message = null,
    ) {}

    public function isUnhealthy(): bool
    {
        return ! $this->healthy;
    }
}
