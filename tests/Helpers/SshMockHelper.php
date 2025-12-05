<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Contracts\SshConnectionContract;
use App\ValueObjects\CommandResult;
use Mockery;
use Mockery\MockInterface;

final class SshMockHelper
{
    public static function mockConnection(): MockInterface
    {
        $mock = Mockery::mock(SshConnectionContract::class);

        $mock->shouldReceive('connect')->andReturnNull();
        $mock->shouldReceive('disconnect')->andReturnNull();
        $mock->shouldReceive('isConnected')->andReturn(true);

        return $mock;
    }

    public static function mockSuccessfulExecution(
        MockInterface $mock,
        string $command,
        string $output = ''
    ): void {
        $mock->shouldReceive('execute')
            ->with($command, Mockery::any())
            ->andReturn(new CommandResult(
                exitCode: 0,
                output: $output,
                command: $command
            ));
    }

    public static function mockFailedExecution(
        MockInterface $mock,
        string $command,
        string $output = '',
        int $exitCode = 1
    ): void {
        $mock->shouldReceive('execute')
            ->with($command, Mockery::any())
            ->andReturn(new CommandResult(
                exitCode: $exitCode,
                output: $output,
                command: $command
            ));
    }

    public static function mockCommandPattern(
        MockInterface $mock,
        string $pattern,
        string $output = '',
        int $exitCode = 0
    ): void {
        $mock->shouldReceive('execute')
            ->with(Mockery::pattern($pattern), Mockery::any())
            ->andReturn(new CommandResult(
                exitCode: $exitCode,
                output: $output,
                command: 'matched-command'
            ));
    }

    public static function mockFileExists(MockInterface $mock, string $path, bool $exists = true): void
    {
        $mock->shouldReceive('fileExists')
            ->with($path)
            ->andReturn($exists);
    }

    public static function mockDirectoryExists(MockInterface $mock, string $path, bool $exists = true): void
    {
        $mock->shouldReceive('directoryExists')
            ->with($path)
            ->andReturn($exists);
    }

    public static function mockUpload(MockInterface $mock, string $localPath, string $remotePath, bool $success = true): void
    {
        $mock->shouldReceive('upload')
            ->with($localPath, $remotePath)
            ->andReturn($success);
    }

    public static function mockDownload(MockInterface $mock, string $remotePath, string $localPath, bool $success = true): void
    {
        $mock->shouldReceive('download')
            ->with($remotePath, $localPath)
            ->andReturn($success);
    }

    /**
     * Mock all common SSH operations for successful deployment.
     */
    public static function mockSuccessfulDeployment(MockInterface $mock): void
    {
        // Mock all execute commands to succeed
        $mock->shouldReceive('execute')
            ->andReturn(new CommandResult(
                exitCode: 0,
                output: 'success',
                command: 'mocked-command'
            ));

        // Mock file operations
        $mock->shouldReceive('fileExists')->andReturn(true);
        $mock->shouldReceive('directoryExists')->andReturn(true);
        $mock->shouldReceive('upload')->andReturn(true);
        $mock->shouldReceive('download')->andReturn(true);
    }

    /**
     * Mock all common SSH operations for successful initialization.
     */
    public static function mockSuccessfulInit(MockInterface $mock): void
    {
        // Mock directory checks - directories don't exist yet
        $mock->shouldReceive('directoryExists')->andReturn(false);

        // Mock disk space check - return 1000MB available
        $mock->shouldReceive('execute')
            ->with(Mockery::pattern('/df -BM/'), Mockery::any())
            ->andReturn(new CommandResult(
                exitCode: 0,
                output: '1000M',
                command: 'df'
            ));

        // Mock permission check
        $mock->shouldReceive('execute')
            ->with(Mockery::pattern('/test -d/'), Mockery::any())
            ->andReturn(new CommandResult(
                exitCode: 0,
                output: '',
                command: 'test'
            ));

        // Mock PHP version check
        $mock->shouldReceive('execute')
            ->with(Mockery::pattern('/php -r/'), Mockery::any())
            ->andReturn(new CommandResult(
                exitCode: 0,
                output: '8.3.0',
                command: 'php'
            ));

        // Mock which git
        $mock->shouldReceive('execute')
            ->with(Mockery::pattern('/which git/'), Mockery::any())
            ->andReturn(new CommandResult(
                exitCode: 0,
                output: '/usr/bin/git',
                command: 'which'
            ));

        // Mock which composer
        $mock->shouldReceive('execute')
            ->with(Mockery::pattern('/which composer/'), Mockery::any())
            ->andReturn(new CommandResult(
                exitCode: 0,
                output: '/usr/bin/composer',
                command: 'which'
            ));

        // Mock mkdir commands
        $mock->shouldReceive('execute')
            ->with(Mockery::pattern('/mkdir/'), Mockery::any())
            ->andReturn(new CommandResult(
                exitCode: 0,
                output: '',
                command: 'mkdir'
            ));

        // Mock any other commands
        $mock->shouldReceive('execute')
            ->andReturn(new CommandResult(
                exitCode: 0,
                output: 'success',
                command: 'mocked-command'
            ));
    }
}
