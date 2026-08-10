<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Claim;

/**
 * Resolves payout-destination icon asset URLs from the packaged
 * resources/documents/payout-destination-icons.json metadata file.
 *
 * This is additive, presentation-only metadata: banks/EMIs/rails/providers
 * that are not covered simply resolve to a null icon, and callers must keep
 * rendering the existing text labels regardless of icon availability.
 */
final class PayoutDestinationIconCatalog
{
    private const PUBLIC_BASE_PATH = '/vendor/x-change/images/payout-destinations/';

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $entries = null;

    /**
     * @return array{slug: string, kind: string, confidence: string, icon_asset: string|null}|null
     */
    public function forCode(mixed $code): ?array
    {
        $normalized = $this->normalizeCode($code);

        if ($normalized === null) {
            return null;
        }

        $entry = $this->entries()[$normalized] ?? null;

        if (! is_array($entry)) {
            return null;
        }

        return [
            'slug' => (string) ($entry['slug'] ?? ''),
            'kind' => (string) ($entry['kind'] ?? 'unknown'),
            'confidence' => (string) ($entry['confidence'] ?? 'low'),
            'icon_asset' => $this->publicAssetUrl($entry),
        ];
    }

    public function iconAssetForCode(mixed $code): ?string
    {
        return $this->forCode($code)['icon_asset'] ?? null;
    }

    public function iconAssetForRail(mixed $rail): ?string
    {
        $normalized = $this->normalizeSegment($rail);

        return $normalized === null ? null : $this->iconAssetForCode("RAIL:{$normalized}");
    }

    public function iconAssetForProvider(mixed $provider): ?string
    {
        $normalized = $this->normalizeSegment($provider);

        return $normalized === null ? null : $this->iconAssetForCode("PROVIDER:{$normalized}");
    }

    public function orchestratorIconAsset(): ?string
    {
        return $this->iconAssetForCode('ORCHESTRATOR:XCHANGE');
    }

    /** @return array<string, array<string, mixed>> */
    private function entries(): array
    {
        if (self::$entries !== null) {
            return self::$entries;
        }

        $path = $this->metadataPath();

        if (! is_file($path)) {
            return self::$entries = [];
        }

        $contents = file_get_contents($path);
        $decoded = is_string($contents) ? json_decode($contents, true) : null;

        $entries = is_array($decoded) && is_array($decoded['entries'] ?? null)
            ? $decoded['entries']
            : [];

        return self::$entries = $entries;
    }

    private function metadataPath(): string
    {
        return dirname(__DIR__, 3).'/resources/documents/payout-destination-icons.json';
    }

    /** @param array<string, mixed> $entry */
    private function publicAssetUrl(array $entry): ?string
    {
        $assets = (array) ($entry['assets'] ?? []);
        $file = $assets['png128'] ?? $assets['png64'] ?? $assets['png256'] ?? $assets['svg'] ?? null;

        if (! is_string($file) || $file === '') {
            return null;
        }

        return self::PUBLIC_BASE_PATH.ltrim($file, '/');
    }

    private function normalizeCode(mixed $code): ?string
    {
        if (! is_scalar($code)) {
            return null;
        }

        $normalized = strtoupper(trim((string) $code));

        return $normalized === '' ? null : $normalized;
    }

    private function normalizeSegment(mixed $value): ?string
    {
        return $this->normalizeCode($value);
    }
}
