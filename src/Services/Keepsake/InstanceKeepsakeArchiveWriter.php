<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Keepsake;

use Composer\InstalledVersions;
use LBHurtado\XChange\Data\Keepsake\InstanceKeepsakePlan;
use LBHurtado\XChange\Exceptions\InstanceKeepsakeException;
use ZipArchive;

final readonly class InstanceKeepsakeArchiveWriter
{
    public function __construct(
        private CanonicalKeepsakeJson $json,
        private InstanceKeepsakeCrypto $crypto,
        private InstanceKeepsakeSchemaValidator $schemas,
    ) {}

    /** @return array{encrypted_path:string,archive_sha256:string,manifest_sha256:string,entry_count:int} */
    public function write(InstanceKeepsakePlan $plan, string $publicKey): array
    {
        if (! class_exists(ZipArchive::class) || ! function_exists('sodium_crypto_secretstream_xchacha20poly1305_init_push')) {
            throw new InstanceKeepsakeException('archive_unavailable', 'ZIP and Sodium support are required for keepsake creation.');
        }

        $workspace = sys_get_temp_dir().'/x-change-keepsake-'.bin2hex(random_bytes(12));
        $requiredTemporaryBytes = max(20 * 1024 * 1024, ($plan->artifactBytes * 3) + (10 * 1024 * 1024));
        $freeTemporaryBytes = disk_free_space(sys_get_temp_dir());

        if (is_float($freeTemporaryBytes) && $freeTemporaryBytes < $requiredTemporaryBytes) {
            throw new InstanceKeepsakeException('limit_exceeded', 'The keepsake temporary workspace does not have enough free space.');
        }

        if (! mkdir($workspace, 0700, true) && ! is_dir($workspace)) {
            throw new InstanceKeepsakeException('storage_unavailable', 'The keepsake temporary workspace could not be created.');
        }

        $zipPath = $workspace.'/keepsake.zip';
        $encryptedPath = $workspace.'/keepsake.xck';

        try {
            $entries = $this->entries($plan);
            $manifestEntries = [];

            foreach ($entries as $path => $entry) {
                $manifestEntries[] = [
                    'path' => $path,
                    'mime_type' => $entry['mime_type'],
                    'size' => strlen($entry['contents']),
                    'sha256' => hash('sha256', $entry['contents']),
                ];
            }

            $manifest = $this->json->encode([
                'schema' => 'x-change.instance-keepsake.manifest.v1',
                'plan_hash' => $plan->hash,
                'observed_at' => $plan->observedAt,
                'created_at' => now('UTC')->toIso8601String(),
                'package_version' => InstalledVersions::getPrettyVersion('3neti/x-change') ?? 'dev',
                'complete' => $plan->omissionCount === 0,
                'omission_count' => $plan->omissionCount,
                'encrypted' => true,
                'restoration_authority' => false,
                'entries' => $manifestEntries,
            ]);
            $this->schemas->validate('manifest.json', $manifest);
            $manifestHash = hash('sha256', $manifest);
            $entries['manifest.json'] = ['contents' => $manifest, 'mime_type' => 'application/json'];
            $entries['manifest.sha256'] = ['contents' => $manifestHash."  manifest.json\n", 'mime_type' => 'text/plain'];
            ksort($entries);

            $zip = new ZipArchive;

            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new InstanceKeepsakeException('archive_unavailable', 'The keepsake ZIP archive could not be created.');
            }

            foreach ($entries as $path => $entry) {
                if (! $zip->addFromString($path, $entry['contents'])) {
                    $zip->close();
                    throw new InstanceKeepsakeException('archive_unavailable', 'A keepsake archive entry could not be written.');
                }
            }

            if (! $zip->close()) {
                throw new InstanceKeepsakeException('archive_unavailable', 'The keepsake ZIP archive could not be finalized.');
            }

            chmod($zipPath, 0600);

            try {
                $this->crypto->encrypt($zipPath, $encryptedPath, $publicKey);
            } finally {
                @unlink($zipPath);
            }

            return [
                'encrypted_path' => $encryptedPath,
                'archive_sha256' => hash_file('sha256', $encryptedPath),
                'manifest_sha256' => $manifestHash,
                'entry_count' => count($entries),
            ];
        } catch (\Throwable $exception) {
            $this->cleanupDirectory($workspace);
            throw $exception;
        }
    }

    public function cleanup(string $encryptedPath): void
    {
        $this->cleanupDirectory(dirname($encryptedPath));
    }

    /** @return array<string, array{contents:string,mime_type:string}> */
    private function entries(InstanceKeepsakePlan $plan): array
    {
        $entries = [
            'README.txt' => [
                'mime_type' => 'text/plain',
                'contents' => "X-Change Instance Keepsake\n\nThis encrypted archive is a human-appreciable observation. It is not a database backup, an accounting opening balance, an executable migration, or authority to restore funds or run migrate:fresh. Financial values are historical observations and require fresh provider reconciliation. The blueprint directory is inert and contains no importer.\n",
            ],
        ];
        $schemaRoot = dirname(__DIR__, 3).'/resources/schemas/instance-keepsake';

        foreach (glob($schemaRoot.'/*.json') ?: [] as $schemaPath) {
            $contents = file_get_contents($schemaPath);

            if (! is_string($contents) || json_decode($contents, true) === null) {
                throw new InstanceKeepsakeException('schema_invalid', 'A packaged keepsake schema is invalid.');
            }

            $entries['schemas/'.basename($schemaPath)] = [
                'mime_type' => 'application/schema+json',
                'contents' => $contents,
            ];
        }
        $galleryItems = [];

        foreach ($plan->contributions as $contribution) {
            foreach ([...$contribution->snapshotFiles, ...$contribution->blueprintFiles] as $path => $contents) {
                $this->schemas->validate($path, $contents);
                $entries[$path] = ['contents' => $contents, 'mime_type' => 'application/json'];
            }

            foreach ($contribution->artifacts as $artifact) {
                if (! isset($artifact['contents']) || hash('sha256', $artifact['contents']) !== $artifact['sha256']) {
                    throw new InstanceKeepsakeException('integrity_mismatch', 'A materialized evidence artifact failed archive verification.');
                }

                $entries[$artifact['path']] = [
                    'contents' => $artifact['contents'],
                    'mime_type' => $artifact['mime_type'],
                ];
                $galleryItems[] = $artifact['path'];
            }
        }

        $entries['gallery/index.html'] = [
            'mime_type' => 'text/html',
            'contents' => $this->gallery($galleryItems),
        ];

        return $entries;
    }

    /** @param list<string> $paths */
    private function gallery(array $paths): string
    {
        $items = '';

        foreach ($paths as $path) {
            $relative = '../'.htmlspecialchars($path, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $label = htmlspecialchars(basename($path), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $items .= "<figure><img loading=\"lazy\" src=\"{$relative}\" alt=\"{$label}\"><figcaption>{$label}</figcaption></figure>";
        }

        return '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>X-Change Keepsake</title><style>body{font:16px system-ui;margin:2rem;background:#f8fafc;color:#0f172a}main{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem}figure{margin:0;padding:1rem;background:white;border-radius:1rem;box-shadow:0 8px 24px #0f172a14}img{display:block;max-width:100%;max-height:360px;margin:auto}figcaption{margin-top:.75rem;overflow-wrap:anywhere}</style><h1>X-Change Instance Keepsake</h1><p>Private offline evidence gallery. Financial values are not restorable opening balances.</p><main>'.$items.'</main></html>';
    }

    private function cleanupDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (glob($directory.'/*') ?: [] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}
