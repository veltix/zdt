<?php

declare(strict_types=1);

namespace App\Services\PhpseclibFactories;

use App\Contracts\PhpseclibClientFactoryContract;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;

final class PhpseclibClientFactory implements PhpseclibClientFactoryContract
{
    public function createSSH2(string $host, int $port): SSH2
    {
        return new SSH2($host, $port);
    }

    public function createSFTP(string $host, int $port): SFTP
    {
        return new SFTP($host, $port);
    }

    public function loadKey(string $keyContent): object
    {
        return PublicKeyLoader::load($keyContent);
    }
}
