<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\Exceptions\ValidationException;

final readonly class ServerCredentials
{
    public string $keyPath;

    public function __construct(
        public string $host,
        public int $port,
        public string $username,
        string $keyPath,
        public int $timeout,
    ) {
        $this->keyPath = $this->expandPath($keyPath);
        $this->validate();
    }

    private function validate(): void
    {
        if (empty($this->host)) {
            throw new ValidationException('Server host is required');
        }

        if ($this->port < 1 || $this->port > 65535) {
            throw new ValidationException('Server port must be between 1 and 65535');
        }

        if (empty($this->username)) {
            throw new ValidationException('Server username is required');
        }

        if (empty($this->keyPath)) {
            throw new ValidationException('SSH key path is required');
        }

        if ($this->timeout < 1) {
            throw new ValidationException('Timeout must be at least 1 second');
        }
    }

    private function expandPath(string $path): string
    {
        if (str_starts_with($path, '~/')) {
            $home = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? '';
            if ($home) {
                $path = $home.mb_substr($path, 1);
            }
        }

        return $path;
    }
}
