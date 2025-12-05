<?php

declare(strict_types=1);

namespace App\Actions;

use App\Exceptions\DatabaseBackupException;
use App\Services\RemoteExecutor;
use App\ValueObjects\DeploymentConfig;
use App\ValueObjects\Release;
use Psr\Log\LoggerInterface;

final readonly class BackupDatabase
{
    public function __construct(
        private RemoteExecutor $executor,
        private LoggerInterface $logger,
    ) {}

    public function handle(DeploymentConfig $config, Release $release): void
    {
        if (! $config->shouldBackupDatabase()) {
            $this->logger->info('Database backup disabled, skipping');

            return;
        }

        $backupDir = $config->getDeployPath().'/backups';
        $backupFile = $backupDir.'/db-backup-'.$release->name.'.sql.gz';

        $this->executor->execute("mkdir -p {$backupDir}");

        $dbConnection = $config->getDatabaseConnection() ?: 'mysql';

        match ($dbConnection) {
            'mysql', 'mariadb' => $this->backupMySQL($config, $backupFile),
            'pgsql', 'postgres' => $this->backupPostgreSQL($config, $backupFile),
            default => throw new DatabaseBackupException("Unsupported database connection: {$dbConnection}"),
        };

        $this->logger->info("Database backup created: {$backupFile}");

        $this->cleanupOldBackups($config, $backupDir);
    }

    private function backupMySQL(DeploymentConfig $config, string $backupFile): void
    {
        $host = $config->getDatabaseHost() ?: 'localhost';
        $port = $config->getDatabasePort() ?: 3306;
        $database = $config->getDatabaseName();
        $username = $config->getDatabaseUsername();
        $password = $config->getDatabasePassword();

        if (! $database || ! $username) {
            throw new DatabaseBackupException('Database name and username are required for backup');
        }

        $passwordArg = $password ? "MYSQL_PWD='{$password}'" : '';

        $command = "{$passwordArg} mysqldump -h {$host} -P {$port} -u {$username} --single-transaction --quick {$database} | gzip > {$backupFile}";

        $result = $this->executor->execute($command, throwOnError: false);

        if (! $result->isSuccessful()) {
            throw new DatabaseBackupException('MySQL backup failed: '.$result->output);
        }
    }

    private function backupPostgreSQL(DeploymentConfig $config, string $backupFile): void
    {
        $host = $config->getDatabaseHost() ?: 'localhost';
        $port = $config->getDatabasePort() ?: 5432;
        $database = $config->getDatabaseName();
        $username = $config->getDatabaseUsername();
        $password = $config->getDatabasePassword();

        if (! $database || ! $username) {
            throw new DatabaseBackupException('Database name and username are required for backup');
        }

        $passwordEnv = $password ? "PGPASSWORD='{$password}'" : '';

        $command = "{$passwordEnv} pg_dump -h {$host} -p {$port} -U {$username} {$database} | gzip > {$backupFile}";

        $result = $this->executor->execute($command, throwOnError: false);

        if (! $result->isSuccessful()) {
            throw new DatabaseBackupException('PostgreSQL backup failed: '.$result->output);
        }
    }

    private function cleanupOldBackups(DeploymentConfig $config, string $backupDir): void
    {
        $keepBackups = $config->getKeepBackups() ?: 5;

        $command = "cd {$backupDir} && ls -t db-backup-*.sql.gz | tail -n +".($keepBackups + 1).' | xargs -r rm --';

        $this->executor->execute($command, throwOnError: false);

        $this->logger->debug("Cleaned up old backups, keeping last {$keepBackups}");
    }
}
