<?php

declare(strict_types=1);

use LBHurtado\XChange\Exceptions\InstanceKeepsakeException;
use LBHurtado\XChange\Services\Keepsake\InstanceKeepsakeCrypto;

it('round trips an authenticated encrypted stream', function () {
    $crypto = app(InstanceKeepsakeCrypto::class);
    $keys = $crypto->generateKeyPair();
    $source = tempnam(sys_get_temp_dir(), 'keepsake-source-');
    $encrypted = tempnam(sys_get_temp_dir(), 'keepsake-encrypted-');
    $decrypted = tempnam(sys_get_temp_dir(), 'keepsake-decrypted-');
    $contents = str_repeat('private keepsake bytes', 100_000);

    file_put_contents($source, $contents);

    try {
        $crypto->encrypt($source, $encrypted, $keys['public_key']);
        $crypto->decrypt($encrypted, $decrypted, $keys['keypair']);

        expect(file_get_contents($encrypted))->not->toContain('private keepsake bytes')
            ->and(file_get_contents($decrypted))->toBe($contents);
    } finally {
        @unlink($source);
        @unlink($encrypted);
        @unlink($decrypted);
    }
});

it('rejects the wrong private key', function () {
    $crypto = app(InstanceKeepsakeCrypto::class);
    $recipient = $crypto->generateKeyPair();
    $wrong = $crypto->generateKeyPair();
    $source = tempnam(sys_get_temp_dir(), 'keepsake-source-');
    $encrypted = tempnam(sys_get_temp_dir(), 'keepsake-encrypted-');
    $decrypted = tempnam(sys_get_temp_dir(), 'keepsake-decrypted-');
    file_put_contents($source, 'sensitive image bytes');

    try {
        $crypto->encrypt($source, $encrypted, $recipient['public_key']);

        expect(fn () => $crypto->decrypt($encrypted, $decrypted, $wrong['keypair']))
            ->toThrow(InstanceKeepsakeException::class);
    } finally {
        @unlink($source);
        @unlink($encrypted);
        @unlink($decrypted);
    }
});

it('finalizes an encrypted stream whose source is an exact chunk multiple', function () {
    $crypto = app(InstanceKeepsakeCrypto::class);
    $keys = $crypto->generateKeyPair();
    $source = tempnam(sys_get_temp_dir(), 'keepsake-source-');
    $encrypted = tempnam(sys_get_temp_dir(), 'keepsake-encrypted-');
    $decrypted = tempnam(sys_get_temp_dir(), 'keepsake-decrypted-');
    $contents = str_repeat('x', 1024 * 1024);
    file_put_contents($source, $contents);

    try {
        $crypto->encrypt($source, $encrypted, $keys['public_key']);
        $crypto->decrypt($encrypted, $decrypted, $keys['keypair']);

        expect(file_get_contents($decrypted))->toBe($contents);
    } finally {
        @unlink($source);
        @unlink($encrypted);
        @unlink($decrypted);
    }
});
