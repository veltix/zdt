<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\PhpseclibClientFactoryContract;
use App\Contracts\SshConnectionContract;
use App\Services\PhpseclibFactories\PhpseclibClientFactory;
use App\Services\PhpseclibSshConnection;
use App\ValueObjects\ServerCredentials;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

final class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        //
    }

    public function register(): void
    {
        $this->app->singleton(PhpseclibClientFactoryContract::class, PhpseclibClientFactory::class);
        $this->app->singleton(SshConnectionContract::class, fn (\Illuminate\Contracts\Foundation\Application $app) => $app->make(PhpseclibSshConnection::class));

        $this->app->bind(ServerCredentials::class, function (\Illuminate\Contracts\Foundation\Application $app): ServerCredentials {
            $host = getenv('DEPLOY_HOST') ?: (config('deploy.server.host') ?? 'localhost');
            $port = (int) (getenv('DEPLOY_PORT') ?: (config('deploy.server.port') ?? 22));
            $username = getenv('DEPLOY_USERNAME') ?: (config('deploy.server.username') ?? 'deployer');
            $keyPath = getenv('DEPLOY_KEY_PATH') ?: (config('deploy.server.key_path') ?? '~/.ssh/id_rsa');
            $timeout = (int) (getenv('DEPLOY_TIMEOUT') ?: (config('deploy.server.timeout') ?? 300));

            return new ServerCredentials(
                host: $host,
                port: $port,
                username: $username,
                keyPath: $keyPath,
                timeout: $timeout,
            );
        });

        $this->app->singleton(LoggerInterface::class, function (\Illuminate\Contracts\Foundation\Application $app): LoggerInterface {
            $log = $app->make('log');

            return method_exists($log, 'channel') ? $log->channel() : $log;
        });
    }
}
