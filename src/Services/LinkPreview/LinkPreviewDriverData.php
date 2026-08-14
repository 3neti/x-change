<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\LinkPreview;

use InvalidArgumentException;

final readonly class LinkPreviewDriverData
{
    /**
     * @param  list<string>  $pageHosts
     * @param  list<string>  $metadataStrategies
     * @param  list<string>  $imageHosts
     * @param  list<string>  $imageMimeTypes
     */
    public function __construct(
        public string $key,
        public string $label,
        public bool $enabled,
        public string $canonicalizer,
        public array $pageHosts,
        public string $pathPattern,
        public array $metadataStrategies,
        public ?string $oEmbedEndpoint,
        public array $imageHosts,
        public array $imageMimeTypes,
    ) {}

    /**
     * @param  array<string, mixed>  $manifest
     */
    public static function fromManifest(
        array $manifest,
        LinkCanonicalizerRegistry $canonicalizers,
        ?bool $enabledOverride = null,
    ): self {
        if (($manifest['schema_version'] ?? null) !== 'x-change.link-preview-driver.v1') {
            throw new InvalidArgumentException('Link preview driver schema_version is invalid.');
        }

        $key = self::requiredString($manifest['key'] ?? null, 'key');

        if (preg_match('/^[a-z][a-z0-9_-]*$/', $key) !== 1) {
            throw new InvalidArgumentException("Link preview driver key [{$key}] is invalid.");
        }

        $label = self::requiredString($manifest['label'] ?? null, 'label');
        $canonicalizer = self::requiredString(
            data_get($manifest, 'canonicalization.strategy'),
            'canonicalization.strategy',
        );

        if (! $canonicalizers->has($canonicalizer)) {
            throw new InvalidArgumentException(
                "Link preview canonicalizer [{$canonicalizer}] is not registered.",
            );
        }

        $pageHosts = self::hosts(
            data_get($manifest, 'match.hosts'),
            'match.hosts',
        );
        $pathPattern = self::requiredString(
            data_get($manifest, 'match.path_pattern'),
            'match.path_pattern',
        );

        if (@preg_match($pathPattern, '/') === false) {
            throw new InvalidArgumentException("Link preview driver [{$key}] path_pattern is invalid.");
        }

        $metadataStrategies = self::metadataStrategies(
            data_get($manifest, 'metadata.strategies'),
        );
        $oEmbedEndpoint = self::oEmbedEndpoint(
            data_get($manifest, 'metadata.oembed_endpoint'),
            $pageHosts,
            in_array('oembed', $metadataStrategies, true),
        );
        $imageHosts = self::hosts(
            data_get($manifest, 'artwork.hosts'),
            'artwork.hosts',
        );
        $imageMimeTypes = self::imageMimeTypes(
            data_get($manifest, 'artwork.mime_types'),
        );

        return new self(
            key: $key,
            label: $label,
            enabled: $enabledOverride ?? ($manifest['enabled'] ?? false) === true,
            canonicalizer: $canonicalizer,
            pageHosts: $pageHosts,
            pathPattern: $pathPattern,
            metadataStrategies: $metadataStrategies,
            oEmbedEndpoint: $oEmbedEndpoint,
            imageHosts: $imageHosts,
            imageMimeTypes: $imageMimeTypes,
        );
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    public function matches(array $parts): bool
    {
        if (
            strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || ! isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || (isset($parts['port']) && (int) $parts['port'] !== 443)
        ) {
            return false;
        }

        $host = strtolower((string) $parts['host']);
        $path = '/'.ltrim((string) ($parts['path'] ?? ''), '/');

        return in_array($host, $this->pageHosts, true)
            && preg_match($this->pathPattern, $path) === 1;
    }

    private static function requiredString(mixed $value, string $field): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("Link preview driver field [{$field}] is required.");
        }

        return trim($value);
    }

    /**
     * @return list<string>
     */
    private static function hosts(mixed $value, string $field): array
    {
        if (! is_array($value) || $value === []) {
            throw new InvalidArgumentException("Link preview driver field [{$field}] must be a non-empty list.");
        }

        $hosts = [];

        foreach ($value as $host) {
            if (! is_string($host)) {
                throw new InvalidArgumentException("Link preview driver field [{$field}] contains an invalid host.");
            }

            $normalized = strtolower(trim($host));

            if (
                $normalized === ''
                || filter_var($normalized, FILTER_VALIDATE_IP) !== false
                || preg_match(
                    '/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/',
                    $normalized,
                ) !== 1
            ) {
                throw new InvalidArgumentException(
                    "Link preview driver field [{$field}] contains unsafe host [{$normalized}].",
                );
            }

            $hosts[] = $normalized;
        }

        return array_values(array_unique($hosts));
    }

    /**
     * @return list<string>
     */
    private static function metadataStrategies(mixed $value): array
    {
        if (! is_array($value) || $value === []) {
            throw new InvalidArgumentException('Link preview metadata strategies must be a non-empty list.');
        }

        $allowed = ['oembed', 'open_graph'];
        $strategies = [];

        foreach ($value as $strategy) {
            if (! is_string($strategy) || ! in_array($strategy, $allowed, true)) {
                throw new InvalidArgumentException('Link preview metadata strategy is not supported.');
            }

            $strategies[] = $strategy;
        }

        return array_values(array_unique($strategies));
    }

    /**
     * @param  list<string>  $pageHosts
     */
    private static function oEmbedEndpoint(
        mixed $value,
        array $pageHosts,
        bool $required,
    ): ?string {
        if (! is_string($value) || trim($value) === '') {
            if ($required) {
                throw new InvalidArgumentException('Link preview oEmbed endpoint is required.');
            }

            return null;
        }

        $url = trim($value);
        $parts = parse_url($url);

        if (
            ! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || ! in_array(strtolower((string) ($parts['host'] ?? '')), $pageHosts, true)
            || isset($parts['user'])
            || isset($parts['pass'])
            || (isset($parts['port']) && (int) $parts['port'] !== 443)
        ) {
            throw new InvalidArgumentException('Link preview oEmbed endpoint is unsafe.');
        }

        return $url;
    }

    /**
     * @return list<string>
     */
    private static function imageMimeTypes(mixed $value): array
    {
        if (! is_array($value) || $value === []) {
            throw new InvalidArgumentException('Link preview artwork MIME types must be a non-empty list.');
        }

        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        $mimeTypes = [];

        foreach ($value as $mimeType) {
            $normalized = is_string($mimeType) ? strtolower(trim($mimeType)) : '';

            if (! in_array($normalized, $allowed, true)) {
                throw new InvalidArgumentException(
                    "Link preview artwork MIME type [{$normalized}] is not supported.",
                );
            }

            $mimeTypes[] = $normalized;
        }

        return array_values(array_unique($mimeTypes));
    }
}
