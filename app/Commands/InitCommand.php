<?php

declare(strict_types=1);

namespace App\Commands;

use App\Actions\EstablishSshConnection;
use App\Actions\ValidateDeploymentConfig;
use App\Actions\ValidateServerRequirements;
use App\Contracts\SshConnectionContract;
use App\Services\RemoteExecutor;
use LaravelZero\Framework\Commands\Command;
use Throwable;

final class InitCommand extends Command
{
    protected $signature = 'deploy:init {--config=deploy.php}';

    protected $description = 'Initialize deployment structure on remote server';

    public function handle(
        ValidateDeploymentConfig $validateConfig,
        EstablishSshConnection $establishConnection,
        SshConnectionContract $ssh,
        RemoteExecutor $executor,
        ValidateServerRequirements $validateRequirements,
    ): int {
        $this->info('Initializing deployment structure...');

        try {
            $config = $validateConfig->handle($this->option('config'));
            $this->line("Target: {$config->server['host']}");
            $this->line("Deploy path: {$config->getDeployPath()}");

            $this->task('Connecting to server', function () use ($establishConnection, $ssh, $config): void {
                $establishConnection->handle($ssh, $config);
            });

            $this->task('Validating server requirements', function () use ($validateRequirements, $config): void {
                $validateRequirements->handle($config);
            });

            $deployPath = $config->getDeployPath();

            $this->task('Creating directory structure', function () use ($executor, $deployPath): void {
                $executor->execute("mkdir -p {$deployPath}/releases");
                $executor->execute("mkdir -p {$deployPath}/shared/storage/app");
                $executor->execute("mkdir -p {$deployPath}/shared/storage/framework/cache");
                $executor->execute("mkdir -p {$deployPath}/shared/storage/framework/sessions");
                $executor->execute("mkdir -p {$deployPath}/shared/storage/framework/views");
                $executor->execute("mkdir -p {$deployPath}/shared/storage/logs");
                $executor->execute("mkdir -p {$deployPath}/.zdt");
            });

            $ssh->disconnect();

            $this->newLine();
            $this->info('✓ Server initialized successfully');
            $this->newLine();
            $this->line('Next steps:');
            $this->line("1. Create .env file at: {$deployPath}/shared/.env");
            $this->line('2. Run your first deployment: zdt deploy --config='.$this->option('config'));

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Initialization failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
