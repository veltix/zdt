<?php

declare(strict_types=1);

namespace App\ValueObjects;

final readonly class DeploymentResult
{
    public function __construct(
        public bool $success,
        public Release $release,
        public ?string $message = null,
        public ?Release $previousRelease = null,
    ) {}

    public function isFailed(): bool
    {
        return ! $this->success;
    }
}
