<?php

declare(strict_types=1);

namespace App\Commands;

use App\Actions\EstablishSshConnection;
use App\Actions\ValidateDeploymentConfig;
use App\Contracts\SshConnectionContract;
use App\Services\RemoteExecutor;
use LaravelZero\Framework\Commands\Command;
use Throwable;

final class ListReleasesCommand extends Command
{
    /** @var string */
    protected $signature = 'releases:list {--config=deploy.php}';

    /** @var string */
    protected $description = 'List all releases on remote server';

    public function handle(
        ValidateDeploymentConfig $validateConfig,
        EstablishSshConnection $establishConnection,
        SshConnectionContract $ssh,
        RemoteExecutor $executor,
    ): int {
        $this->info('Listing releases...');
        $this->newLine();

        try {
            /** @var string $configOption */
            $configOption = $this->option('config');
            $config = $validateConfig->handle($configOption);
            $establishConnection->handle($ssh, $config);

            $releasesPath = $config->getDeployPath().'/releases';
            $currentSymlink = $config->getDeployPath().'/current';

            $currentResult = $executor->execute("readlink {$currentSymlink}", throwOnError: false);
            $currentRelease = $currentResult->isSuccessful() ? basename(mb_trim($currentResult->output)) : null;

            $listResult = $executor->execute("ls -lt {$releasesPath} | grep '^d' | awk '{print \$9}'");
            $releases = array_filter(explode("\n", mb_trim($listResult->output)));

            if (empty($releases)) {
                $this->warn('No releases found');

                return self::SUCCESS;
            }

            $this->table(
                ['Release', 'Status'],
                collect($releases)->map(function (string $release) use ($currentRelease): array {
                    $status = $release === $currentRelease ? '✓ Active' : '';

                    return [$release, $status];
                })->toArray()
            );

            $this->newLine();
            $this->line('Total releases: '.count($releases));
            if ($currentRelease !== null) {
                $this->line("Current release: {$currentRelease}");
            }

            $ssh->disconnect();

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Failed to list releases: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
