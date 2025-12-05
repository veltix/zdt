<?php

declare(strict_types=1);

namespace App\Contracts;

use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;

interface PhpseclibClientFactoryContract
{
    public function createSSH2(string $host, int $port): SSH2;

    public function createSFTP(string $host, int $port): SFTP;

    public function loadKey(string $keyContent): object;
}
