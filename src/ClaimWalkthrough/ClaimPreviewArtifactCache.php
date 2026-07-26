<?php

declare(strict_types=1);

namespace LBHurtado\XChange\ClaimWalkthrough;

use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Filesystem\Filesystem;
use LBHurtado\XChange\Models\ClaimPreviewArtifact;

final class ClaimPreviewArtifactCache
{
    public function __construct(
        private readonly CacheRepository $cache,
        private readonly Filesystem $files,
        private readonly ClaimPreviewArtifactFingerprint $fingerprints,
    ) {}

    /**
     * @param  array<string, mixed>  $scenario
     * @param  array<string, mixed>  $options
     * @return array{fingerprint: string, payload: array<string, mixed>, root: string, relative_path: string}
     */
    public function context(array $scenario, array $options = []): array
    {
        $fingerprint = $this->fingerprints->make($scenario, $options);
        $relativePath = 'x-change/claim-previews/'.$fingerprint['fingerprint'];

        return [
            'fingerprint' => $fingerprint['fingerprint'],
            'payload' => $fingerprint['payload'],
            'root' => storage_path('app/'.$relativePath),
            'relative_path' => $relativePath,
        ];
    }

    public function find(string $fingerprint): ?ClaimPreviewArtifact
    {
        $cachedId = $this->cache->get($this->key($fingerprint));

        if (is_numeric($cachedId)) {
            $artifact = ClaimPreviewArtifact::query()
                ->whereKey((int) $cachedId)
                ->where('status', 'ready')
                ->first();

            if ($this->isUsable($artifact)) {
                return $artifact;
            }
        }

        $artifact = ClaimPreviewArtifact::query()
            ->where('artifact_fingerprint', $fingerprint)
            ->where('status', 'ready')
            ->latest('id')
            ->first();

        if (! $this->isUsable($artifact)) {
            return null;
        }

        $this->cache->put($this->key($fingerprint), $artifact->getKey(), now()->addDay());

        return $artifact;
    }

    /**
     * @param  array<string, mixed>  $scenario
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $report
     */
    public function rememberRendered(
        array $scenario,
        string $fingerprint,
        string $relativePath,
        string $profile,
        array $payload,
        array $report,
    ): ClaimPreviewArtifact {
        $artifact = ClaimPreviewArtifact::query()->updateOrCreate(
            ['artifact_fingerprint' => $fingerprint],
            [
                'scenario_key' => (string) ($scenario['key'] ?? 'unknown'),
                'scenario_version' => (int) ($scenario['version'] ?? 1),
                'profile' => $profile,
                'status' => 'ready',
                'artifact_disk' => 'local',
                'artifact_path' => $relativePath,
                'metadata' => [
                    'fingerprint_payload' => $payload,
                    'report' => $report,
                ],
                'generated_at' => now(),
                'expires_at' => null,
            ],
        );

        $this->cache->put($this->key($fingerprint), $artifact->getKey(), now()->addDay());

        return $artifact;
    }

    /**
     * @return array<string, mixed>
     */
    public function reportFor(ClaimPreviewArtifact $artifact): array
    {
        $report = data_get($artifact->metadata, 'report', []);

        if (! is_array($report)) {
            $report = [];
        }

        data_set($report, 'cache.hit', true);
        data_set($report, 'cache.artifact_reference', $artifact->reference);
        data_set($report, 'cache.artifact_fingerprint', $artifact->artifact_fingerprint);

        return $report;
    }

    public function ensurePreviewDirectory(string $root): void
    {
        $this->files->ensureDirectoryExists($root);
        $this->files->ensureDirectoryExists($root.'/screenshots');
        $this->files->ensureDirectoryExists($root.'/storyboard-frames');
    }

    private function isUsable(?ClaimPreviewArtifact $artifact): bool
    {
        if (! $artifact instanceof ClaimPreviewArtifact) {
            return false;
        }

        if ($artifact->expires_at !== null && $artifact->expires_at->isPast()) {
            return false;
        }

        return $this->files->exists(storage_path('app/'.$artifact->artifact_path.'/walkthrough-storyboard.pdf'))
            || $this->files->exists(storage_path('app/'.$artifact->artifact_path.'/claim-walkthrough-storyboard.json'));
    }

    private function key(string $fingerprint): string
    {
        return 'x-change:claim-preview-artifact:'.$fingerprint;
    }
}
