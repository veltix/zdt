<?php

declare(strict_types=1);

namespace App\ValueObjects;

use DateTimeImmutable;

final readonly class Release
{
    public function __construct(
        public string $name,
        public string $path,
        public DateTimeImmutable $createdAt,
        public ?string $commitHash = null,
        public ?string $branch = null,
        public bool $isActive = false,
    ) {}

    public static function create(string $deployPath): self
    {
        $timestamp = now()->format('YmdHis');
        $name = $timestamp;
        $path = mb_rtrim($deployPath, '/').'/releases/'.$name;

        return new self(
            name: $name,
            path: $path,
            createdAt: now()->toDateTimeImmutable(),
        );
    }

    public function withCommitHash(string $commitHash): self
    {
        return new self(
            name: $this->name,
            path: $this->path,
            createdAt: $this->createdAt,
            commitHash: $commitHash,
            branch: $this->branch,
            isActive: $this->isActive,
        );
    }

    public function withBranch(string $branch): self
    {
        return new self(
            name: $this->name,
            path: $this->path,
            createdAt: $this->createdAt,
            commitHash: $this->commitHash,
            branch: $branch,
            isActive: $this->isActive,
        );
    }

    public function activate(): self
    {
        return new self(
            name: $this->name,
            path: $this->path,
            createdAt: $this->createdAt,
            commitHash: $this->commitHash,
            branch: $this->branch,
            isActive: true,
        );
    }
}
