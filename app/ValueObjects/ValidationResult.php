<?php

declare(strict_types=1);

namespace App\ValueObjects;

final readonly class ValidationResult
{
    /**
     * @param  array<string, mixed>  $checks
     */
    public function __construct(
        public bool $passed,
        public array $checks = [],
        public ?string $message = null,
    ) {}

    public function isFailed(): bool
    {
        return ! $this->passed;
    }
}
