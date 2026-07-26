<?php

declare(strict_types=1);

namespace LBHurtado\XChange\ClaimWalkthrough;

final class ClaimPreviewArtifactFingerprint
{
    /**
     * @param  array<string, mixed>  $scenario
     * @param  array<string, mixed>  $options
     * @return array{fingerprint: string, payload: array<string, mixed>}
     */
    public function make(array $scenario, array $options = []): array
    {
        $payload = $this->canonicalize([
            'schema_version' => 'x-change.claim-preview.fingerprint.v1',
            'scenario_key' => $scenario['key'] ?? null,
            'scenario_version' => $scenario['version'] ?? 1,
            'fixture' => $scenario['fixture'] ?? [],
            'profile' => $options['profile'] ?? 'issuer',
            'dry_run' => (bool) ($options['dry_run'] ?? false),
            'submit_claim' => (bool) ($options['submit_claim'] ?? false),
            'mobile' => $options['mobile'] ?? null,
            'bank_code' => $options['bank_code'] ?? null,
            'account_number' => $options['account_number'] ?? null,
            'viewport' => $options['viewport'] ?? 'desktop',
            'locale' => app()->getLocale(),
            'asset_fingerprint' => $this->assetFingerprint(),
        ]);

        return [
            'fingerprint' => hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
            'payload' => $payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function canonicalize(array $payload): array
    {
        ksort($payload);

        return array_map(function (mixed $value): mixed {
            if (is_array($value)) {
                return array_is_list($value)
                    ? array_map(fn (mixed $item): mixed => is_array($item) ? $this->canonicalize($item) : $item, $value)
                    : $this->canonicalize($value);
            }

            return $value;
        }, $payload);
    }

    private function assetFingerprint(): ?string
    {
        $manifest = public_path('build/manifest.json');

        if (! is_file($manifest)) {
            return null;
        }

        return hash_file('sha256', $manifest) ?: null;
    }
}
