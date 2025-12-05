<?php

declare(strict_types=1);

namespace App\Actions;

use App\Exceptions\RollbackException;
use App\Services\RemoteExecutor;
use App\ValueObjects\Release;
use Psr\Log\LoggerInterface;

final readonly class ValidateRollbackTarget
{
    public function __construct(
        private RemoteExecutor $executor,
        private LoggerInterface $logger,
    ) {}

    public function handle(Release $target): void
    {
        $this->logger->info("Validating rollback target: {$target->name}");

        $checkDir = $this->executor->execute(
            "test -d {$target->path}",
            throwOnError: false
        );

        if ($checkDir->isFailed()) {
            throw new RollbackException("Target release not found: {$target->name}");
        }

        $checkIndex = $this->executor->execute(
            "test -f {$target->path}/index.php || test -f {$target->path}/artisan",
            throwOnError: false
        );

        if ($checkIndex->isFailed()) {
            throw new RollbackException("Target release appears to be incomplete: {$target->name}");
        }

        $this->logger->info('Rollback target is valid');
    }
}
