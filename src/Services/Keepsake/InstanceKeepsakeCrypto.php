<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Keepsake;

use LBHurtado\XChange\Exceptions\InstanceKeepsakeException;

final class InstanceKeepsakeCrypto
{
    private const MAGIC = "XCKEEP1\n";

    /** @return array{keypair:string,public_key:string} */
    public function generateKeyPair(): array
    {
        $keypair = sodium_crypto_box_keypair();

        return [
            'keypair' => $keypair,
            'public_key' => base64_encode(sodium_crypto_box_publickey($keypair)),
        ];
    }

    public function encrypt(string $source, string $destination, string $encodedPublicKey): void
    {
        $publicKey = base64_decode(trim($encodedPublicKey), true);

        if (! is_string($publicKey) || strlen($publicKey) !== SODIUM_CRYPTO_BOX_PUBLICKEYBYTES) {
            throw new InstanceKeepsakeException('encryption_unavailable', 'The configured keepsake public key is invalid.');
        }

        $input = fopen($source, 'rb');
        $output = fopen($destination, 'wb');

        if (! is_resource($input) || ! is_resource($output)) {
            throw new InstanceKeepsakeException('storage_unavailable', 'The keepsake encryption workspace is unavailable.');
        }

        $key = null;

        try {
            try {
                chmod($destination, 0600);
                $sourceSize = filesize($source);

                if (! is_int($sourceSize) || $sourceSize < 1) {
                    throw new InstanceKeepsakeException('storage_unavailable', 'The keepsake archive is empty or unreadable.');
                }

                $key = random_bytes(SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES);
                [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
                $sealedKey = sodium_crypto_box_seal($key, $publicKey);
                $this->writeExactly($output, self::MAGIC);
                $this->writeExactly($output, pack('n', strlen($sealedKey)));
                $this->writeExactly($output, $sealedKey);
                $this->writeExactly($output, $header);

                while (! feof($input)) {
                    $chunk = fread($input, 1024 * 1024);

                    if (! is_string($chunk)) {
                        throw new InstanceKeepsakeException('storage_unavailable', 'The keepsake archive could not be read for encryption.');
                    }

                    if ($chunk === '' && feof($input)) {
                        break;
                    }

                    $tag = ftell($input) >= $sourceSize
                        ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL
                        : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
                    $ciphertext = sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk, '', $tag);
                    $this->writeExactly($output, pack('N', strlen($ciphertext)));
                    $this->writeExactly($output, $ciphertext);
                }

                if (! fflush($output)) {
                    throw new InstanceKeepsakeException('storage_unavailable', 'The encrypted keepsake could not be flushed safely.');
                }
            } finally {
                fclose($input);
                fclose($output);
            }

            $this->verifyEncryptedOutput($destination, $key);
        } finally {
            if (is_string($key)) {
                sodium_memzero($key);
            }
        }
    }

    public function decrypt(string $source, string $destination, string $keypair): void
    {
        if (strlen($keypair) !== SODIUM_CRYPTO_BOX_KEYPAIRBYTES) {
            throw new InstanceKeepsakeException('decryption_failed', 'The keepsake private key file is invalid.');
        }

        $input = fopen($source, 'rb');
        $output = fopen($destination, 'wb');

        if (! is_resource($input) || ! is_resource($output)) {
            throw new InstanceKeepsakeException('storage_unavailable', 'The keepsake decryption workspace is unavailable.');
        }

        try {
            chmod($destination, 0600);

            if (fread($input, strlen(self::MAGIC)) !== self::MAGIC) {
                throw new InstanceKeepsakeException('decryption_failed', 'The file is not an X-Change keepsake archive.');
            }

            $lengthBytes = fread($input, 2);
            $length = is_string($lengthBytes) ? unpack('nlength', $lengthBytes)['length'] ?? 0 : 0;
            $sealedKey = fread($input, $length);
            $header = fread($input, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
            $key = is_string($sealedKey) ? sodium_crypto_box_seal_open($sealedKey, $keypair) : false;

            if (! is_string($key) || ! is_string($header)) {
                throw new InstanceKeepsakeException('decryption_failed', 'The keepsake archive cannot be opened with this private key.');
            }

            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $key);
            sodium_memzero($key);
            $finalSeen = false;

            while (! feof($input)) {
                $frameLengthBytes = fread($input, 4);

                if ($frameLengthBytes === '' && feof($input)) {
                    break;
                }

                $frameLength = is_string($frameLengthBytes)
                    ? unpack('Nlength', $frameLengthBytes)['length'] ?? 0
                    : 0;
                $ciphertext = $this->readExactly($input, $frameLength);
                $opened = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $ciphertext);

                if (! is_array($opened)) {
                    throw new InstanceKeepsakeException('decryption_failed', 'The keepsake archive authentication failed.');
                }

                [$plaintext, $tag] = $opened;
                $this->writeExactly($output, $plaintext);
                $finalSeen = $tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;

                if ($finalSeen && ! feof($input)) {
                    break;
                }
            }

            if (! $finalSeen) {
                throw new InstanceKeepsakeException('decryption_failed', 'The keepsake archive is incomplete.');
            }
        } finally {
            fclose($input);
            fclose($output);
        }
    }

    /** @param resource $stream */
    private function readExactly($stream, int $length): string
    {
        if ($length < 1 || $length > 1024 * 1024 + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES) {
            throw new InstanceKeepsakeException('decryption_failed', 'The keepsake archive contains an invalid encrypted frame.');
        }

        $contents = '';

        while (strlen($contents) < $length && ! feof($stream)) {
            $chunk = fread($stream, $length - strlen($contents));

            if (! is_string($chunk)) {
                break;
            }

            $contents .= $chunk;
        }

        if (strlen($contents) !== $length) {
            throw new InstanceKeepsakeException('decryption_failed', 'The keepsake archive contains a truncated encrypted frame.');
        }

        return $contents;
    }

    /** @param resource $stream */
    private function writeExactly($stream, string $contents): void
    {
        $written = 0;
        $length = strlen($contents);

        while ($written < $length) {
            $bytes = fwrite($stream, substr($contents, $written));

            if (! is_int($bytes) || $bytes < 1) {
                throw new InstanceKeepsakeException('storage_unavailable', 'The keepsake archive could not be written completely.');
            }

            $written += $bytes;
        }
    }

    private function verifyEncryptedOutput(string $path, ?string $key): void
    {
        if (! is_string($key)) {
            throw new InstanceKeepsakeException('encryption_unavailable', 'The keepsake encryption key was not initialized.');
        }

        $stream = fopen($path, 'rb');

        if (! is_resource($stream)) {
            throw new InstanceKeepsakeException('storage_unavailable', 'The encrypted keepsake could not be reopened for verification.');
        }

        try {
            if ($this->readExactly($stream, strlen(self::MAGIC)) !== self::MAGIC) {
                throw new InstanceKeepsakeException('integrity_mismatch', 'The encrypted keepsake framing is invalid.');
            }

            $sealedKeyLength = unpack('nlength', $this->readExactly($stream, 2))['length'] ?? 0;
            $this->readExactly($stream, $sealedKeyLength);
            $header = $this->readExactly($stream, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $key);
            $finalSeen = false;

            while (! feof($stream)) {
                $lengthBytes = fread($stream, 4);

                if ($lengthBytes === '' && feof($stream)) {
                    break;
                }

                if (! is_string($lengthBytes) || strlen($lengthBytes) !== 4) {
                    throw new InstanceKeepsakeException('integrity_mismatch', 'The encrypted keepsake framing is incomplete.');
                }

                $frameLength = unpack('Nlength', $lengthBytes)['length'] ?? 0;
                $opened = sodium_crypto_secretstream_xchacha20poly1305_pull(
                    $state,
                    $this->readExactly($stream, $frameLength),
                );

                if (! is_array($opened)) {
                    throw new InstanceKeepsakeException('integrity_mismatch', 'The encrypted keepsake failed authentication.');
                }

                $finalSeen = $opened[1] === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;

                if ($finalSeen) {
                    if (fread($stream, 1) !== '') {
                        throw new InstanceKeepsakeException('integrity_mismatch', 'The encrypted keepsake contains data after its final frame.');
                    }

                    break;
                }
            }

            if (! $finalSeen) {
                throw new InstanceKeepsakeException('integrity_mismatch', 'The encrypted keepsake is missing its final frame.');
            }
        } finally {
            fclose($stream);
        }
    }
}
