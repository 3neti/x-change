<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\LinkPreview;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

final class LinkPreviewEngine
{
    public function __construct(
        private readonly LinkPreviewDriverRepository $drivers,
        private readonly LinkCanonicalizerRegistry $canonicalizers,
    ) {}

    /**
     * @return array{
     *     available: bool,
     *     source: string,
     *     title: string,
     *     description: string,
     *     image_url: ?string,
     *     public_image_url: ?string,
     *     reference: string
     * }
     */
    public function resolve(string $url): array
    {
        $resolvedDriver = $this->resolveDriver($url);

        if ($resolvedDriver === null) {
            return $this->unavailable();
        }

        [$driver, $canonicalUrl] = $resolvedDriver;
        $cacheKey = 'x-change:cockpit:rider-url-artwork:v2:'.hash(
            'sha256',
            $driver->key.'|'.$canonicalUrl,
        );
        $cacheTtl = max(
            60,
            (int) config(
                'x-change.cockpit.quick_generate.url_artwork.cache_ttl_seconds',
                3600,
            ),
        );
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        $resolved = $this->resolveProviderArtwork($driver, $canonicalUrl);

        Cache::put(
            $cacheKey,
            $resolved,
            $resolved['available'] ? $cacheTtl : min(60, $cacheTtl),
        );

        return $resolved;
    }

    /**
     * @return null|array{LinkPreviewDriverData, string}
     */
    private function resolveDriver(string $url): ?array
    {
        foreach ($this->drivers->all() as $driver) {
            $canonicalUrl = $this->canonicalizers->canonicalize(
                $driver->canonicalizer,
                $url,
                $driver,
            );

            if ($canonicalUrl !== null) {
                return [$driver, $canonicalUrl];
            }
        }

        return null;
    }

    /**
     * @return array{
     *     available: bool,
     *     source: string,
     *     title: string,
     *     description: string,
     *     image_url: ?string,
     *     public_image_url: ?string,
     *     reference: string
     * }
     */
    private function resolveProviderArtwork(
        LinkPreviewDriverData $driver,
        string $canonicalUrl,
    ): array {
        $metadata = $this->providerMetadata($driver, $canonicalUrl);
        $imageUrl = $this->safeArtworkUrl(
            $metadata['image'] ?? null,
            $driver->imageHosts,
        );
        $imageDataUrl = $imageUrl === null
            ? null
            : $this->fetchArtworkDataUrl(
                $imageUrl,
                $driver->imageMimeTypes,
            );

        if ($imageDataUrl === null) {
            return $this->unavailable();
        }

        return [
            'available' => true,
            'source' => $driver->key,
            'title' => $this->safeText(
                $metadata['title'] ?? null,
                $driver->label,
                160,
            ),
            'description' => $this->safeText(
                $metadata['description'] ?? null,
                $driver->label,
                240,
            ),
            'image_url' => $imageDataUrl,
            'public_image_url' => $imageUrl,
            'reference' => $driver->label,
        ];
    }

    /**
     * @return array{title?: string, description?: string, image?: string}
     */
    private function providerMetadata(
        LinkPreviewDriverData $driver,
        string $canonicalUrl,
    ): array {
        foreach ($driver->metadataStrategies as $strategy) {
            $metadata = match ($strategy) {
                'oembed' => $driver->oEmbedEndpoint === null
                    ? []
                    : $this->oEmbedMetadata(
                        $driver->oEmbedEndpoint,
                        $canonicalUrl,
                    ),
                'open_graph' => $this->pageMetadata($canonicalUrl),
                default => [],
            };

            if (isset($metadata['image'])) {
                return $metadata;
            }
        }

        return [];
    }

    /**
     * @return array{title?: string, description?: string, image?: string}
     */
    private function oEmbedMetadata(string $endpoint, string $url): array
    {
        try {
            $response = $this->metadataRequest()
                ->acceptJson()
                ->get($endpoint, ['url' => $url]);
        } catch (Throwable) {
            return [];
        }

        $maximumBytes = max(
            1024,
            (int) config(
                'x-change.cockpit.quick_generate.url_artwork.maximum_metadata_bytes',
                64 * 1024,
            ),
        );

        if (
            ! $response->successful()
            || ! str_starts_with(
                strtolower((string) $response->header('Content-Type')),
                'application/json',
            )
            || $response->body() === ''
            || strlen($response->body()) > $maximumBytes
        ) {
            return [];
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            return [];
        }

        return [
            'title' => $payload['title'] ?? null,
            'description' => $payload['provider_name'] ?? null,
            'image' => $payload['thumbnail_url'] ?? null,
        ];
    }

    /**
     * @return array{title?: string, description?: string, image?: string}
     */
    private function pageMetadata(string $url): array
    {
        try {
            $response = $this->metadataRequest()
                ->accept('text/html, application/xhtml+xml')
                ->get($url);
        } catch (Throwable) {
            return [];
        }

        $contentType = strtolower(trim(explode(
            ';',
            (string) $response->header('Content-Type'),
        )[0]));
        $document = $response->body();
        $maximumDocumentBytes = max(
            1024,
            (int) config(
                'x-change.cockpit.quick_generate.url_artwork.maximum_document_bytes',
                512 * 1024,
            ),
        );

        if (
            ! $response->successful()
            || ! in_array($contentType, ['text/html', 'application/xhtml+xml'], true)
            || $document === ''
            || strlen($document) > $maximumDocumentBytes
        ) {
            return [];
        }

        return $this->openGraphMetadata($document);
    }

    private function metadataRequest(): PendingRequest
    {
        return Http::connectTimeout(max(
            1,
            (int) config(
                'x-change.cockpit.quick_generate.url_artwork.connect_timeout_seconds',
                3,
            ),
        ))
            ->timeout(max(
                1,
                (int) config(
                    'x-change.cockpit.quick_generate.url_artwork.timeout_seconds',
                    6,
                ),
            ))
            ->withoutRedirecting();
    }

    /**
     * @return array{title?: string, description?: string, image?: string}
     */
    private function openGraphMetadata(string $document): array
    {
        $dom = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            if (! $dom->loadHTML($document, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR)) {
                return [];
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $metadata = [];

        foreach ($dom->getElementsByTagName('meta') as $meta) {
            $property = strtolower(trim(
                $meta->getAttribute('property') ?: $meta->getAttribute('name'),
            ));
            $content = trim($meta->getAttribute('content'));

            if ($content === '') {
                continue;
            }

            $key = match ($property) {
                'og:title' => 'title',
                'og:description' => 'description',
                'og:image', 'og:image:secure_url', 'twitter:image' => 'image',
                default => null,
            };

            if ($key !== null && ! isset($metadata[$key])) {
                $metadata[$key] = $content;
            }
        }

        return $metadata;
    }

    /**
     * @param  list<string>  $approvedHosts
     */
    private function safeArtworkUrl(mixed $value, array $approvedHosts): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $url = trim($value);
        $parts = parse_url($url);

        if (
            $url === ''
            || ! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || ! isset($parts['host'])
            || ! in_array(strtolower((string) $parts['host']), $approvedHosts, true)
            || isset($parts['user'])
            || isset($parts['pass'])
            || (isset($parts['port']) && (int) $parts['port'] !== 443)
        ) {
            return null;
        }

        return $url;
    }

    /**
     * @param  list<string>  $allowedMimeTypes
     */
    private function fetchArtworkDataUrl(
        string $url,
        array $allowedMimeTypes,
    ): ?string {
        try {
            $response = Http::accept(implode(', ', $allowedMimeTypes))
                ->connectTimeout(max(
                    1,
                    (int) config(
                        'x-change.cockpit.quick_generate.url_artwork.connect_timeout_seconds',
                        3,
                    ),
                ))
                ->timeout(max(
                    1,
                    (int) config(
                        'x-change.cockpit.quick_generate.url_artwork.timeout_seconds',
                        6,
                    ),
                ))
                ->withoutRedirecting()
                ->get($url);
        } catch (Throwable) {
            return null;
        }

        $mimeType = strtolower(trim(explode(
            ';',
            (string) $response->header('Content-Type'),
        )[0]));
        $body = $response->body();
        $maximumBytes = max(
            1024,
            (int) config(
                'x-change.cockpit.quick_generate.url_artwork.maximum_image_bytes',
                2 * 1024 * 1024,
            ),
        );

        if (
            ! $response->successful()
            || ! in_array($mimeType, $allowedMimeTypes, true)
            || $body === ''
            || strlen($body) > $maximumBytes
        ) {
            return null;
        }

        return 'data:'.$mimeType.';base64,'.base64_encode($body);
    }

    private function safeText(mixed $value, string $fallback, int $limit): string
    {
        if (! is_string($value)) {
            return $fallback;
        }

        $text = trim(preg_replace(
            '/\s+/',
            ' ',
            html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5),
        ) ?? '');

        return $text === '' ? $fallback : Str::limit($text, $limit);
    }

    /**
     * @return array{
     *     available: bool,
     *     source: string,
     *     title: string,
     *     description: string,
     *     image_url: null,
     *     public_image_url: null,
     *     reference: string
     * }
     */
    private function unavailable(): array
    {
        return [
            'available' => false,
            'source' => 'link',
            'title' => 'Action Link',
            'description' => 'Artwork is not available for this link.',
            'image_url' => null,
            'public_image_url' => null,
            'reference' => 'Action URL',
        ];
    }
}
