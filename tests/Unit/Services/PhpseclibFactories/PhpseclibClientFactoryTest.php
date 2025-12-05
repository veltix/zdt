<?php

declare(strict_types=1);

use App\Services\PhpseclibFactories\PhpseclibClientFactory;
use phpseclib3\Crypt\RSA;
use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;

test('createSSH2 returns SSH2 instance', function () {
    $factory = new PhpseclibClientFactory();
    $ssh = $factory->createSSH2('localhost', 22);

    expect($ssh)->toBeInstanceOf(SSH2::class);
});

test('createSFTP returns SFTP instance', function () {
    $factory = new PhpseclibClientFactory();
    $sftp = $factory->createSFTP('localhost', 22);

    expect($sftp)->toBeInstanceOf(SFTP::class);
});

test('loadKey returns key object', function () {
    $factory = new PhpseclibClientFactory();

    // Generate a valid key using phpseclib itself to ensure compatibility
    $key = RSA::createKey();
    $privateKey = $key->toString('OpenSSH');

    $loadedKey = $factory->loadKey($privateKey);

    expect($loadedKey)->toBeObject();
    // verifying exactly what it is depends on phpseclib version/implementation,
    // but toBeObject is sufficient to prove the method ran and returned something.
});
