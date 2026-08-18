<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Keepsake;

use LBHurtado\XChange\Exceptions\InstanceKeepsakeException;
use ZipArchive;

final readonly class VerifyInstanceKeepsakeArchive
{
    public function __construct(private InstanceKeepsakeCrypto $crypto) {}

    /** @return array<string, mixed> */
    public function handle(
        string $archivePath,
        string $keyPath,
        string $expectedArchiveHash,
        ?string $extractTo = null,
    ): array {
        if (! is_file($archivePath) || ! is_readable($archivePath)) {
            throw new InstanceKeepsakeException('archive_unavailable', 'The encrypted keepsake file is not readable.');
        }

        $archiveHash = hash_file('sha256', $archivePath);

        if (preg_match('/^[a-f0-9]{64}$/', $expectedArchiveHash) !== 1
            || ! hash_equals($expectedArchiveHash, $archiveHash)) {
            throw new InstanceKeepsakeException('integrity_mismatch', 'The downloaded archive checksum does not match the export record.');
        }

        $keypair = is_file($keyPath) ? file_get_contents($keyPath) : false;

        if (! is_string($keypair)) {
            throw new InstanceKeepsakeException('decryption_failed', 'The keepsake private key file is not readable.');
        }

        $temporaryZip = tempnam(sys_get_temp_dir(), 'x-change-keepsake-');

        if (! is_string($temporaryZip)) {
            throw new InstanceKeepsakeException('storage_unavailable', 'A verification workspace could not be created.');
        }

        try {
            chmod($temporaryZip, 0600);
            $this->crypto->decrypt($archivePath, $temporaryZip, $keypair);
            $zip = new ZipArchive;

            if ($zip->open($temporaryZip, ZipArchive::RDONLY) !== true) {
                throw new InstanceKeepsakeException('integrity_mismatch', 'The decrypted keepsake is not a readable ZIP archive.');
            }

            try {
                $manifest = $zip->getFromName('manifest.json');
                $manifestChecksum = $zip->getFromName('manifest.sha256');

                if (! is_string($manifest) || ! is_string($manifestChecksum)) {
                    throw new InstanceKeepsakeException('integrity_mismatch', 'The keepsake manifest is missing.');
                }

                $expectedManifestHash = strtok(trim($manifestChecksum), ' ');

                if (! is_string($expectedManifestHash) || ! hash_equals($expectedManifestHash, hash('sha256', $manifest))) {
                    throw new InstanceKeepsakeException('integrity_mismatch', 'The keepsake manifest checksum does not match.');
                }

                $decoded = json_decode($manifest, true, flags: JSON_THROW_ON_ERROR);
                $entries = is_array($decoded['entries'] ?? null) ? $decoded['entries'] : [];

                foreach ($entries as $entry) {
                    $path = (string) ($entry['path'] ?? '');
                    $this->assertSafePath($path);
                    $contents = $zip->getFromName($path);

                    if (! is_string($contents)
                        || strlen($contents) !== (int) ($entry['size'] ?? -1)
                        || ! hash_equals((string) ($entry['sha256'] ?? ''), hash('sha256', $contents))) {
                        throw new InstanceKeepsakeException('integrity_mismatch', 'A keepsake entry failed checksum verification.');
                    }
                }

                if ($extractTo !== null) {
                    $this->extract($zip, $extractTo);
                }

                return [
                    'schema' => 'x-change.instance-keepsake-verification.v1',
                    'status' => 'verified_outside_creation_service',
                    'archive_sha256' => $archiveHash,
                    'manifest_sha256' => hash('sha256', $manifest),
                    'plan_hash' => $decoded['plan_hash'] ?? null,
                    'entry_count' => count($entries),
                    'complete' => (bool) ($decoded['complete'] ?? false),
                    'safe_to_reset' => false,
                    'migrate_fresh_invoked' => false,
                ];
            } finally {
                $zip->close();
            }
        } finally {
            @unlink($temporaryZip);
            sodium_memzero($keypair);
        }
    }

    private function extract(ZipArchive $zip, string $directory): void
    {
        if (file_exists($directory)) {
            throw new InstanceKeepsakeException('extract_destination_exists', 'The extraction destination already exists.');
        }

        if (! mkdir($directory, 0700, true)) {
            throw new InstanceKeepsakeException('storage_unavailable', 'The extraction destination could not be created.');
        }

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $path = $zip->getNameIndex($index);

                if (! is_string($path)) {
                    continue;
                }

                $this->assertSafePath($path);
                $destination = $directory.'/'.$path;
                $parent = dirname($destination);

                if (! is_dir($parent) && ! mkdir($parent, 0700, true) && ! is_dir($parent)) {
                    throw new InstanceKeepsakeException('storage_unavailable', 'A keepsake extraction directory could not be created.');
                }

                $contents = $zip->getFromIndex($index);

                if (! is_string($contents) || file_put_contents($destination, $contents, LOCK_EX) === false) {
                    throw new InstanceKeepsakeException('storage_unavailable', 'A keepsake entry could not be extracted.');
                }

                chmod($destination, 0600);
            }
        } catch (\Throwable $exception) {
            $this->removeDirectory($directory);
            throw $exception;
        }
    }

    private function assertSafePath(string $path): void
    {
        if ($path === ''
            || str_starts_with($path, '/')
            || preg_match('/(^|\/)\.\.?(\/|$)/', $path) === 1
            || preg_match('/[\x00-\x1F\x7F\\\\]/', $path) === 1) {
            throw new InstanceKeepsakeException('unsafe_archive_path', 'The keepsake contains an unsafe archive path.');
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory.'/'.$entry;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}
