<?php

declare(strict_types=1);

namespace App\ValueObjects;

final readonly class CommandResult
{
    public function __construct(
        public int $exitCode,
        public string $output,
        public string $command,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->exitCode === 0;
    }

    public function isFailed(): bool
    {
        return ! $this->isSuccessful();
    }
}
